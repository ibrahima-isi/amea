<?php
namespace Amea\Tests\Integration\Service;

use Amea\Service\FileLifecycleService;
use Amea\Storage\LocalStorage;
use Amea\Storage\StorageException;
use Amea\Tests\Support\FailingStorage;
use PHPUnit\Framework\TestCase;

class FileLifecycleServiceTest extends TestCase
{
    private string $root;
    private FailingStorage $storage;
    private \PDO $db;
    private FileLifecycleService $service;
    private ?\Aws\S3\S3Client $client = null;
    private ?string $bucket = null;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/amea-lifecycle-' . bin2hex(random_bytes(8));
        mkdir($this->root);
        $inner = new LocalStorage($this->root);
        if (getenv('STORAGE_TEST_ENDPOINT')) {
            $this->bucket = 'amea-member-test-' . bin2hex(random_bytes(8));
            $this->client = new \Aws\S3\S3Client([
                'version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => getenv('STORAGE_TEST_ENDPOINT'),
                'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'], 'use_path_style_endpoint' => true,
                'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
            ]);
            $this->client->createBucket(['Bucket' => $this->bucket]);
            $inner = new \Amea\Storage\S3Storage($this->client, $this->bucket);
        }
        $this->storage = new FailingStorage($inner);
        $this->db = makeTestDb();
        $this->service = new FileLifecycleService($this->db, $this->storage, 'test-local');
    }

    protected function tearDown(): void
    {
        if ($this->client !== null) {
            foreach ($this->client->getPaginator('ListObjectsV2', ['Bucket' => $this->bucket]) as $page) {
                foreach ($page['Contents'] ?? [] as $object) { $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $object['Key']]); }
            }
            $this->client->deleteBucket(['Bucket' => $this->bucket]);
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($this->root);
    }

    private function put(string $key): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "%PDF-1.4\nSynthetic fixture\n%%EOF");
        rewind($stream);
        $this->storage->write($key, $stream, 'application/pdf', 1234567890);
        fclose($stream);
    }

    public function testPendingCleanupUsesOriginalAgeAndRetainsRecentNestedObjects(): void
    {
        $old = 'uploads/students/pending/nested/old.pdf';
        $recent = 'uploads/students/pending/nested/recent.pdf';
        $this->put('uploads/migration-original.pdf');
        $this->storage->copy('uploads/migration-original.pdf', $old);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'recent'); rewind($stream);
        $this->storage->write($recent, $stream, 'text/plain', 2000000000);
        fclose($stream);
        $cleanup = new \Amea\Service\PendingUploadCleanupService($this->db, $this->storage, $this->service);
        $cleanup->sweep(24, 2000000001);
        self::assertNull($this->storage->stat($old));
        self::assertNotNull($this->storage->stat($recent));
    }

    public function testOrdinaryCleanupCannotContinueAnInterruptedPurge(): void
    {
        $key = 'uploads/students/interrupted-purge.pdf';
        $this->put($key);
        $this->db->prepare('INSERT INTO students (id, cv_path, deleted_at) VALUES (90, ?, ?)')->execute([$key, '2020-01-01']);
        $this->storage->failDelete = true;
        self::assertFalse($this->service->purgeMember(90));
        $this->storage->failDelete = false;
        self::assertSame(0, $this->service->retryDeletions(100, false));
        self::assertNotNull((new \Amea\Repository\StudentRepository($this->db))->findTrashedById(90));
        self::assertNotNull($this->storage->stat($key));
        self::assertSame(1, $this->service->retryDeletions(100, true));
    }

    public function testPendingCleanupFailureRemainsRetryableAndRechecksOriginalAge(): void
    {
        $key = 'uploads/students/pending/nested/retry.pdf';
        $this->put($key);
        $cleanup = new \Amea\Service\PendingUploadCleanupService($this->db, $this->storage, $this->service);
        $this->storage->failList = true;
        try { $cleanup->sweep(24, 2000000000); self::fail('Listing failures must propagate'); }
        catch (StorageException) { self::assertNotNull($this->storage->stat($key)); }
        $this->storage->failList = false;
        $this->storage->failDelete = true;
        self::assertSame(0, $cleanup->sweep(24, 2000000000));
        self::assertTrue($this->service->hasPendingDeletions(false));
        $this->storage->failDelete = false;
        self::assertSame(1, $cleanup->sweep(24, 2000000000));
        self::assertNull($this->storage->stat($key));
        self::assertFalse($this->service->hasPendingDeletions(false));
    }

    public function testPendingRetryRetainsAnObjectRecreatedWithRecentOriginalAge(): void
    {
        $key = 'uploads/students/pending/recreated.pdf';
        $this->put($key);
        $this->storage->failDelete = true;
        (new \Amea\Service\PendingUploadCleanupService($this->db, $this->storage, $this->service))->sweep(24, 2000000000);
        $this->storage->failDelete = false;
        $this->storage->delete($key);
        $stream = fopen('php://temp', 'w+b'); fwrite($stream, 'new content'); rewind($stream);
        $this->storage->write($key, $stream, 'text/plain', 2000000000); fclose($stream);
        self::assertSame(1, $this->service->retryDeletions(10, false));
        self::assertNotNull($this->storage->stat($key));
    }

    public function testCleanupRetainsActiveMediaAndHistoricalJournalCovers(): void
    {
        $slider = 'uploads/slider/shared.jpg';
        $history = 'uploads/journal/covers/historical.jpg';
        $this->put($slider); $this->put($history);
        $this->db->prepare('INSERT INTO slider_images (image_path) VALUES (?)')->execute([$slider]);
        $this->db->beginTransaction();
        $this->service->scheduleDeletion('media-shared', [$slider, $history], 'slider-removal');
        $this->db->commit();
        self::assertSame(1, $this->service->retryDeletions(10, false));
        self::assertNotNull($this->storage->stat($slider));
        self::assertNotNull($this->storage->stat($history));
    }

    public function testSecondDocumentCopyFailureRetainsSourcesAndCanBeRetried(): void
    {
        $identity = 'uploads/students/pending/identity.pdf';
        $cv = 'uploads/students/pending/cvs/cv.pdf';
        $this->put($identity);
        $this->put($cv);
        $map = [$identity => 'uploads/students/identity.pdf', $cv => 'uploads/students/cvs/cv.pdf'];
        $this->storage->copiesUntilFailure = 1;
        try {
            $this->service->prepareFinalization($map);
            self::fail('A partial finalization must fail');
        } catch (StorageException) {
            self::assertNotNull($this->storage->stat($identity));
            self::assertNotNull($this->storage->stat($cv));
        }
        self::assertSame($map, $this->service->prepareFinalization($map));
        self::assertSame($map, $this->service->prepareFinalization($map));
        self::assertNotNull($this->storage->stat($identity));
    }
    public function testSaveFailureKeepsPreviousReferenceAndStagedFiles(): void
    {
        $old = 'uploads/students/cvs/old.pdf';
        $new = 'uploads/students/pending/cvs/new.pdf';
        $this->put($old);
        $this->put($new);
        $this->db->exec("INSERT INTO students (id, cv_path) VALUES (10, '$old')");
        $this->db->exec("CREATE TRIGGER fail_save BEFORE UPDATE ON students BEGIN SELECT RAISE(ABORT, 'Injected DB failure'); END");
        try {
            $this->service->saveMember(['cv_path' => $new], [], 10);
            self::fail('The DB failure must propagate');
        } catch (\PDOException) {
            self::assertSame($old, (new \Amea\Repository\StudentRepository($this->db))->findById(10)->getCvPath());
            self::assertNotNull($this->storage->stat($old));
            self::assertNotNull($this->storage->stat($new));
        }
    }

    public function testCommittedCleanupSurvivesInterruptionAndDeleteFailure(): void
    {
        $key = 'uploads/students/retired.pdf';
        $this->put($key);
        $this->db->beginTransaction();
        $this->service->scheduleDeletion('retired', [$key], 'replacement');
        $this->db->commit();
        $this->storage->failDelete = true;
        self::assertSame(0, $this->service->retryDeletions(10));
        self::assertNotNull($this->storage->stat($key));
        $this->storage->failDelete = false;
        $restarted = new FileLifecycleService($this->db, $this->storage, 'test-local');
        self::assertSame(1, $restarted->retryDeletions(10));
        self::assertNull($this->storage->stat($key));
        self::assertSame(0, $restarted->retryDeletions(10));
    }

    public function testCleanupRetainsFilesReferencedByTrashedMemberOrAnotherColumn(): void
    {
        $key = 'uploads/students/shared.pdf';
        $this->put($key);
        $this->db->prepare('INSERT INTO students (identity_document, deleted_at) VALUES (?, ?)')->execute([$key, '2026-01-01']);
        $this->db->beginTransaction();
        $this->service->scheduleDeletion('shared', [$key], 'replacement');
        $this->db->commit();
        $this->service->retryDeletions(10);
        self::assertNotNull($this->storage->stat($key));
    }

    public function testPurgeFailureRetainsTargetAndPreventsRestoreUntilRetryCompletes(): void
    {
        $key = 'uploads/students/purge.pdf';
        $this->put($key);
        $this->db->prepare('INSERT INTO students (id, identity_document, deleted_at) VALUES (20, ?, ?)')->execute([$key, '2026-01-01']);
        $this->storage->failDelete = true;
        self::assertFalse($this->service->purgeMember(20));
        self::assertNotNull((new \Amea\Repository\StudentRepository($this->db))->findTrashedById(20));
        self::assertFalse($this->service->restoreMember(20));
        $this->storage->failDelete = false;
        self::assertSame(1, $this->service->retryDeletions(10));
        self::assertNull((new \Amea\Repository\StudentRepository($this->db))->findTrashedById(20));
        self::assertNull($this->storage->stat($key));
    }

    public function testAutomaticPurgeRechecksRetentionAndManualPurgeRetainsSharedFile(): void
    {
        $key = 'uploads/students/shared-purge.pdf';
        $this->put($key);
        $this->db->prepare('INSERT INTO students (id, cv_path, deleted_at) VALUES (21, ?, ?)')->execute([$key, date('Y-m-d H:i:s')]);
        $this->db->prepare('INSERT INTO students (id, identity_document) VALUES (22, ?)')->execute([$key]);
        self::assertFalse($this->service->purgeMember(21, 30));
        self::assertTrue($this->service->purgeMember(21));
        self::assertNotNull($this->storage->stat($key));
    }

    public function testPartialPurgeRetainsAllKeysAcrossProcessInterruption(): void
    {
        $identity = 'uploads/students/partial-purge.pdf';
        $cv = 'uploads/students/cvs/partial-purge.pdf';
        $this->put($identity);
        $this->put($cv);
        $this->db->prepare('INSERT INTO students (id, identity_document, cv_path, deleted_at) VALUES (30, ?, ?, ?)')->execute([$identity, $cv, '2026-01-01']);
        $this->storage->deletesUntilFailure = 1;
        self::assertFalse($this->service->purgeMember(30));
        self::assertNull($this->storage->stat($identity));
        self::assertNotNull($this->storage->stat($cv));
        self::assertFalse($this->service->restoreMember(30));
        $restarted = new FileLifecycleService($this->db, $this->storage, 'test-local');
        self::assertSame(1, $restarted->retryDeletions(10));
        self::assertNull((new \Amea\Repository\StudentRepository($this->db))->findTrashedById(30));
    }

    public function testReferenceCommitSurvivesProcessExitBeforeSourceCleanup(): void
    {
        $old = 'uploads/students/cvs/before-interruption.pdf';
        $new = 'uploads/students/pending/cvs/after-interruption.pdf';
        $this->put($old);
        $this->put($new);
        $this->db->prepare('INSERT INTO students (id, cv_path) VALUES (31, ?)')->execute([$old]);
        $this->service->saveMember(['cv_path' => $new], [], 31);
        $member = (new \Amea\Repository\StudentRepository($this->db))->findById(31);
        self::assertSame('uploads/students/cvs/after-interruption.pdf', $member->getCvPath());
        self::assertNotNull($this->storage->stat($new));
        self::assertNotNull($this->storage->stat($old));
        $restarted = new FileLifecycleService($this->db, $this->storage, 'test-local');
        self::assertSame(1, $restarted->retryDeletions(10));
        self::assertNull($this->storage->stat($new));
        self::assertNull($this->storage->stat($old));
        self::assertNotNull($this->storage->stat($member->getCvPath()));
    }

}
