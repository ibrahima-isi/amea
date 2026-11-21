<?php
namespace Tests\Integration\Controller;

use Amea\Service\JournalMediaService;
use Amea\Storage\LocalStorage;
use PHPUnit\Framework\TestCase;

final class MediaStorageTest extends TestCase
{
    private string $root;
    private \Amea\Storage\Storage $storage;
    private ?\Aws\S3\S3Client $client = null;
    private ?string $bucket = null;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/amea-media-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700);
        $this->storage = new LocalStorage($this->root);
        if (getenv('STORAGE_TEST_ENDPOINT')) {
            $this->client = new \Aws\S3\S3Client([
                'version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => getenv('STORAGE_TEST_ENDPOINT'),
                'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'], 'use_path_style_endpoint' => true,
                'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
            ]);
            $this->bucket = 'amea-media-test-' . bin2hex(random_bytes(6));
            $this->client->createBucket(['Bucket' => $this->bucket]);
            $this->storage = new \Amea\Storage\S3Storage($this->client, $this->bucket);
        }
    }

    protected function tearDown(): void
    {
        if ($this->client !== null) {
            foreach ($this->client->getPaginator('ListObjectsV2', ['Bucket' => $this->bucket]) as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $object['Key']]);
                }
            }
            $this->client->deleteBucket(['Bucket' => $this->bucket]);
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
        rmdir($this->root);
    }

    public function testCarouselSaveCannotPublishAnUnreadableObjectThroughHttp(): void
    {
        $command = ['python3', dirname(__DIR__, 2) . '/http/media-storage.py'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output);
    }

    public function testJournalReadsVerifiedImageThroughSelectedStorage(): void
    {
        $key = 'uploads/journal/covers/' . str_repeat('a', 32) . '.jpg';
        $source = fopen(__DIR__ . '/../../../assets/img/presidents.jpg', 'rb');
        $this->storage->write($key, $source, 'image/jpeg', 100);
        fclose($source);
        $media = new JournalMediaService($this->root, $this->storage);
        $image = $media->open($key);
        self::assertSame('image/jpeg', $image['mime']);
        self::assertSame(hash_file('sha256', __DIR__ . '/../../../assets/img/presidents.jpg'), hash('sha256', stream_get_contents($image['stream'])));
        fclose($image['stream']);
    }

    public function testReconciliationListsEveryPageAndKeepsCompleteKeys(): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'synthetic');
        for ($number = 0; $number < 1001; $number++) {
            rewind($stream);
            $this->storage->write(sprintf('uploads/students/nested/%04d.pdf', $number), $stream, 'text/plain', 100);
        }
        rewind($stream);
        $this->storage->write('uploads/students/0000.pdf', $stream, 'text/plain', 100);
        fclose($stream);
        $reconcile = new \Amea\Service\DocumentReconcileService(null, $this->root, $this->storage);
        $keys = $reconcile->scanUploadFiles(['uploads/students/', 'uploads/students/nested/']);
        self::assertCount(1002, $keys);
        self::assertContains('uploads/students/nested/1000.pdf', $keys);
        self::assertSame(['uploads/students/nested/0000.pdf'], $reconcile->findOrphanedFiles(['uploads/students/nested/0000.pdf'], ['uploads/students/0000.pdf']));
        self::assertSame('missing', $reconcile->classifyDocument('uploads/students/absent.pdf', [])['status']);
    }

    public function testReconciliationStorageFailureCannotBecomeMissing(): void
    {
        $storage = new \Amea\Tests\Support\FailingStorage($this->storage);
        $storage->failStat = true;
        $reconcile = new \Amea\Service\DocumentReconcileService(null, $this->root, $storage);
        $this->expectException(\Amea\Storage\StorageException::class);
        $reconcile->classifyDocument('uploads/students/missing.pdf', []);
    }
}
