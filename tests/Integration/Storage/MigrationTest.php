<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\Migration\Copier;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\StorageException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../storage/migration-fixture.php';

class MigrationTest extends TestCase
{
    private array $fixture;
    private array $identity;

    protected function setUp(): void
    {
        $this->fixture = migrationFixture(sys_get_temp_dir() . '/amea-migration-' . bin2hex(random_bytes(8)));
        $this->identity = ['environment' => 'synthetic', 'database' => ReferenceAudit::databaseIdentity(new \PDO('sqlite::memory:')),
            'source' => (new LocalStorage($this->fixture['source']))->migrationIdentity(),
            'destination' => (new LocalStorage($this->fixture['destination']))->migrationIdentity(),
            'sessions' => $this->fixture['sessions'], 'serializer' => 'php'];
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fixture['root'], \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->fixture['root']);
    }

    private function inventory(): Inventory
    {
        return new Inventory(new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['source'], 2);
    }

    public function testReadOnlyInventoryPreservesAllOriginalFilesAndReportsSpecialEntries(): void
    {
        $manifest = $this->inventory()->scan();
        self::assertCount(12, $manifest->files);
        self::assertArrayHasKey('uploads/students/cvs/shared.pdf', $manifest->files);
        self::assertArrayHasKey('uploads/students/identity/shared.pdf', $manifest->files);
        self::assertContains('symlink', array_column($manifest->issues, 'type'));
        self::assertContains('unknown-directory', array_column($manifest->issues, 'type'));
        foreach ($this->fixture['files'] as $key => $bytes) {
            self::assertSame($bytes, file_get_contents($this->fixture['source'] . '/' . $key));
            self::assertSame(1600000000, filemtime($this->fixture['source'] . '/' . $key));
        }
        self::assertDirectoryDoesNotExist($this->fixture['source'] . '/storage');
        $manifest->save($this->fixture['manifest']);
        self::assertSame($manifest->files, Manifest::load($this->fixture['manifest'], $this->identity)->files);
        self::assertSame(0600, fileperms($this->fixture['manifest']) & 0777);
    }

    public function testInterruptedUploadResumesByReadingBackDestinationAndKeepsSource(): void
    {
        $source = new LocalStorage($this->fixture['source']);
        $destination = new class($this->fixture['destination']) extends LocalStorage {
            private bool $interrupt = true;
            public function write(string $key, mixed $source, string $mime, int $createdAt): array
            {
                $result = parent::write($key, $source, $mime, $createdAt);
                if ($this->interrupt) {
                    $this->interrupt = false;
                    throw new StorageException('Synthetic interruption after upload before checkpoint.');
                }
                return $result;
            }
        };
        $manifest = $this->inventory()->scan();
        $manifest->save($this->fixture['manifest']);
        $copier = new Copier($source, $destination, $this->identity, $this->inventory());
        try {
            $copier->copy($manifest);
            self::fail('Interruption must propagate.');
        } catch (StorageException $error) {
            self::assertStringContainsString('Synthetic interruption', $error->getMessage());
        }
        $copier->copy(Manifest::load($this->fixture['manifest'], $this->identity));
        foreach ($this->fixture['files'] as $key => $bytes) {
            $stream = $destination->read($key);
            self::assertSame($bytes, stream_get_contents($stream));
            fclose($stream);
            self::assertNotNull($source->stat($key));
            self::assertSame(1600000000, $destination->stat($key)['createdAt']);
        }
        $current = Manifest::load($this->fixture['manifest'], $this->identity);
        self::assertSame(['verified'], array_values(array_unique(array_column($current->files, 'verify_state'))));
        self::assertContains('symlink', array_column($copier->verify($current), 'type'));
        $current->locked(function (Manifest $manifest) {
            $manifest->map('uploads/unknown/page-0.bin', 'uploads/remapped/page-0.bin', hash('sha256', 'page 0'));
            $manifest->checkpoint();
        });
        $mapped = Manifest::load($this->fixture['manifest'], $this->identity)->files['uploads/unknown/page-0.bin'];
        self::assertSame('pending', $mapped['copy_state']);
        self::assertSame('pending', $mapped['verify_state']);
    }

    public function testAuditIncludesDatabaseLedgerAndPrivatePhpSessionOwnersWithoutTokens(): void
    {
        $db = makeTestDb();
        $db->exec("INSERT INTO students (identity_document, cv_path, deleted_at) VALUES ('uploads/students/identity/shared.pdf', 'missing.pdf', '2026-01-01')");
        $db->exec("INSERT INTO slider_images (image_path) VALUES ('shared.pdf')");
        $db->prepare('INSERT INTO file_operations (operation_id, backend, reason, keys_json, expected_identity, expected_cv) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(['synthetic-operation', $this->identity['source'], 'purge-member', json_encode(['uploads/unknown/unreferenced.bin']), 'uploads/students/identity/shared.pdf', 'uploads/students/cvs/shared.pdf']);
        $manifest = $this->inventory()->scan();
        $audit = new ReferenceAudit($db, new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['sessions'], 'php');
        $differences = $audit->audit($manifest);
        self::assertContains('missing', array_column($differences, 'state'));
        self::assertContains('ambiguous', array_column($differences, 'state'));
        self::assertCount(7, $manifest->references);
        self::assertSame(['session'], array_values(array_unique(array_column(array_filter($manifest->references, static fn ($row) => $row['owner_type'] === 'session'), 'owner_type'))));
        $json = json_encode($manifest->references, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('synthetic-secret-token', $json);
        self::assertStringNotContainsString('never-export', $json);
        self::assertStringContainsString('expected_cv', $json);
        self::assertSame('uploads/students/pending/old.pdf', $manifest->references[array_key_last($manifest->references)]['resolved_key']);
    }

    public function testEqualSizeAndMtimeSourceMutationCannotBeSkippedOrOverwriteHistory(): void
    {
        $manifest = $this->inventory()->scan();
        $manifest->save($this->fixture['manifest']);
        $copier = new Copier(new LocalStorage($this->fixture['source']), new LocalStorage($this->fixture['destination']), $this->identity, $this->inventory());
        $copier->copy($manifest);
        $key = 'uploads/unknown/page-0.bin';
        file_put_contents($this->fixture['source'] . '/' . $key, 'change');
        touch($this->fixture['source'] . '/' . $key, 1600000000);
        try {
            $copier->copy($manifest);
            self::fail('Equal timestamps and sizes cannot hide changed bytes.');
        } catch (StorageException $error) {
            self::assertStringContainsString('differs', $error->getMessage());
        }
        $current = Manifest::load($this->fixture['manifest'], $this->identity);
        self::assertContains('changed', array_column($current->history, 'type'));
        self::assertSame('change', file_get_contents($this->fixture['source'] . '/' . $key));
        self::assertSame('page 0', file_get_contents($this->fixture['destination'] . '/' . $key));
        $mapping = [['source_key' => $key, 'destination_key' => 'uploads/reconciled/page-0.bin', 'expected_sha256' => hash('sha256', 'change')]];
        $copier->copy($current, $mapping, hash('sha256', json_encode($mapping, JSON_THROW_ON_ERROR)));
        self::assertSame('change', file_get_contents($this->fixture['destination'] . '/uploads/reconciled/page-0.bin'));
        self::assertSame('page 0', file_get_contents($this->fixture['destination'] . '/' . $key));
    }

    public function testReviewedCorrectionsUseSharedMigrationLockAndExpectedOldTransaction(): void
    {
        if (getenv('MIGRATION_TEST_DB') !== '1') {
            self::markTestSkipped('Select a disposable migrated MariaDB through DB_* and MIGRATION_TEST_DB=1.');
        }
        $runtime = \Amea\Migration\MigrationRuntime::fromEnvironment();
        $db = $runtime->connection()->getNativeConnection();
        $db->exec("INSERT INTO students (last_name, first_name, gender, status, identity_document) VALUES ('Synthetic', 'Migration', 'Masculin', 'ETUDIANT', 'shared.pdf')");
        $id = (string) $db->lastInsertId();
        $this->identity['database'] = ReferenceAudit::databaseIdentity($db);
        $manifest = $this->inventory()->scan();
        $audit = new ReferenceAudit($db, new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['sessions'], 'php');
        $audit->audit($manifest);
        $manifest->save($this->fixture['manifest']);
        $destination = new LocalStorage($this->fixture['destination']);
        (new Copier(new LocalStorage($this->fixture['source']), $destination, $this->identity, $this->inventory()))->copy($manifest);
        $mappings = [['table' => 'students', 'record' => $id, 'column' => 'identity_document', 'expected_old' => 'shared.pdf', 'new_value' => 'uploads/students/identity/shared.pdf']];
        $digest = hash('sha256', json_encode($mappings, JSON_THROW_ON_ERROR));
        $audit->correct($manifest, $mappings, $digest, $destination, $runtime);
        $audit->correct($manifest, $mappings, $digest, $destination, $runtime);
        $audit->audit($manifest);
        $rows = array_values(array_filter($manifest->references, static fn ($row) => $row['table'] === 'students' && $row['record'] === $id));
        self::assertSame('uploads/students/identity/shared.pdf', $rows[0]['original_value']);
        self::assertSame('resolved', $rows[0]['state']);
        self::assertSame('applied', Manifest::load($this->fixture['manifest'], $this->identity)->corrections[0]['state']);
        $manifest->locked(function (Manifest $manifest) use ($audit) {
            $audit->audit($manifest);
            $manifest->checkpoint();
        });
        $audit->correct($manifest, $mappings, $digest, $destination, $runtime);
        $mappingPath = $this->fixture['root'] . '/evidence/corrections.json';
        file_put_contents($mappingPath, json_encode($mappings, JSON_THROW_ON_ERROR));
        chmod($mappingPath, 0600);
        $environment = array_merge(getenv(), ['MIGRATION_DB_DSN' => '', 'MIGRATION_ENVIRONMENT' => 'synthetic',
            'FILE_WRITE_GATE_PATH' => $this->fixture['root'] . '/gate',
            'MIGRATION_SOURCE_DRIVER' => 'local', 'MIGRATION_SOURCE_ROOT' => $this->fixture['source'],
            'MIGRATION_DESTINATION_DRIVER' => 'local', 'MIGRATION_DESTINATION_ROOT' => $this->fixture['destination'],
            'MIGRATION_SESSION_PATH' => $this->fixture['sessions'], 'MIGRATION_SESSION_SERIALIZER' => 'php']);
        [$code, $output] = $this->cli('audit-references', ['--environment=synthetic', '--database=' . $this->identity['database'],
            '--source=' . $this->identity['source'], '--destination=' . $this->identity['destination'], '--manifest=' . $this->fixture['manifest'],
            '--review-manifest=' . $manifest->reviewIdentity(), '--corrections=' . $mappingPath, '--review-corrections=' . hash_file('sha256', $mappingPath)], $environment);
        self::assertSame(1, $code, 'Reference correction requires the exclusive persistent pause.');
        [$pauseCode, $pauseOutput] = $this->cli('pause', ['--environment=synthetic', '--database=' . $this->identity['database'],
            '--source=' . $this->identity['source'], '--destination=' . $this->identity['destination'], '--manifest=' . $this->fixture['manifest']], $environment);
        self::assertSame(0, $pauseCode, $pauseOutput);
        [$code, $output] = $this->cli('audit-references', ['--environment=synthetic', '--database=' . $this->identity['database'],
            '--source=' . $this->identity['source'], '--destination=' . $this->identity['destination'], '--manifest=' . $this->fixture['manifest'],
            '--review-manifest=' . $manifest->reviewIdentity(), '--corrections=' . $mappingPath, '--review-corrections=' . hash_file('sha256', $mappingPath)], $environment);
        self::assertContains($code, [0, 3], $output); // Other deliberately retained fixture references can remain unresolved.
        self::assertSame('applied', Manifest::load($this->fixture['manifest'], $this->identity)->corrections[0]['state']);
        $db->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
    }

    public function testActualCliRequiresReviewedTargetAndReverseCopiesCurrentNewReferences(): void
    {
        $databasePath = $this->fixture['root'] . '/fixture.sqlite';
        makeTestDb()->exec('VACUUM INTO ' . (new \PDO('sqlite::memory:'))->quote($databasePath));
        $database = new \PDO('sqlite:' . $databasePath);
        $this->identity['database'] = ReferenceAudit::databaseIdentity($database);
        $environment = array_merge(getenv(), [
            'MIGRATION_DB_DSN' => 'sqlite:' . $databasePath, 'MIGRATION_ENVIRONMENT' => 'synthetic',
            'FILE_WRITE_GATE_PATH' => $this->fixture['root'] . '/gate',
            'MIGRATION_SOURCE_DRIVER' => 'local', 'MIGRATION_SOURCE_ROOT' => $this->fixture['source'],
            'MIGRATION_DESTINATION_DRIVER' => 'local', 'MIGRATION_DESTINATION_ROOT' => $this->fixture['destination'],
            'MIGRATION_SESSION_PATH' => $this->fixture['sessions'], 'MIGRATION_SESSION_SERIALIZER' => 'php',
        ]);
        $arguments = ['--environment=synthetic', '--database=' . $this->identity['database'],
            '--source=' . $this->identity['source'], '--destination=' . $this->identity['destination'], '--manifest=' . $this->fixture['manifest']];
        $mappingPath = $this->fixture['root'] . '/evidence/duplicate-mappings.json';
        $duplicateMappings = [
            ['source_key' => 'uploads/unknown/page-0.bin', 'destination_key' => 'uploads/mapped-a.bin', 'expected_sha256' => hash('sha256', 'page 0')],
            ['source_key' => 'uploads/unknown/page-0.bin', 'destination_key' => 'uploads/mapped-b.bin', 'expected_sha256' => hash('sha256', 'page 0')],
        ];
        file_put_contents($mappingPath, json_encode($duplicateMappings, JSON_THROW_ON_ERROR));
        chmod($mappingPath, 0600);
        [$code] = $this->cli('inventory', [...$arguments, '--mappings=' . $mappingPath, '--review-mappings=' . hash_file('sha256', $mappingPath)], $environment);
        self::assertSame(2, $code);
        self::assertFileDoesNotExist($this->fixture['manifest']);
        mkdir($this->fixture['source'] . '/private', 0700);
        $unsafeArguments = $arguments;
        $unsafeArguments[array_key_last($unsafeArguments)] = '--manifest=' . $this->fixture['source'] . '/private/manifest.jsonl';
        [$code] = $this->cli('inventory', $unsafeArguments, $environment);
        self::assertSame(2, $code);
        self::assertFileDoesNotExist($this->fixture['source'] . '/private/manifest.jsonl');
        [$code, $output] = $this->cli('inventory', $arguments, $environment);
        self::assertSame(0, $code, $output);
        self::assertDirectoryDoesNotExist($this->fixture['source'] . '/storage');
        [$code] = $this->cli('copy', $arguments, $environment);
        self::assertSame(2, $code);
        self::assertDirectoryDoesNotExist($this->fixture['destination'] . '/uploads');
        $manifest = Manifest::load($this->fixture['manifest'], $this->identity);
        [$code, $output] = $this->cli('copy', [...$arguments, '--review-manifest=' . $manifest->reviewIdentity()], $environment);
        self::assertSame(0, $code, $output);
        [$code] = $this->cli('verify', $arguments, $environment);
        self::assertSame(3, $code); // Unclassified symlink and unknown directory remain visible.
        $dispositions = array_map(static fn ($issue) => ['type' => 'issue', 'key' => $issue['key'], 'issue_type' => $issue['type'],
            'decision' => 'Synthetic exception reviewed, retain source and backup.'], $manifest->issues);
        $dispositionPath = $this->fixture['root'] . '/evidence/dispositions.json';
        file_put_contents($dispositionPath, json_encode($dispositions, JSON_THROW_ON_ERROR));
        chmod($dispositionPath, 0600);
        [$code, $output] = $this->cli('pause', $arguments, $environment);
        self::assertSame(0, $code, $output);
        [$code, $output] = $this->cli('verify', [...$arguments, '--review-manifest=' . $manifest->reviewIdentity(),
            '--dispositions=' . $dispositionPath, '--review-dispositions=' . hash_file('sha256', $dispositionPath)], $environment);
        self::assertSame(0, $code, $output);

        (new \Amea\Core\FileWriteGate($this->fixture['root'] . '/gate'))->resume(static fn () => null);
        $new = 'uploads/students/cvs/new-submission.pdf';
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "%PDF-1.4\nnew submission\n");
        rewind($stream);
        (new LocalStorage($this->fixture['destination']))->write($new, $stream, 'application/pdf', 1700000000);
        fclose($stream);
        $database->prepare('INSERT INTO students (cv_path) VALUES (?)')->execute([$new]);
        $reverseIdentity = $this->identity;
        $reverseIdentity['source'] = $this->identity['destination'];
        $reverseIdentity['destination'] = $this->identity['source'];
        $environment['MIGRATION_SOURCE_ROOT'] = $this->fixture['destination'];
        $environment['MIGRATION_DESTINATION_ROOT'] = $this->fixture['source'];
        $reverseArguments = ['--environment=synthetic', '--database=' . $reverseIdentity['database'], '--source=' . $reverseIdentity['source'],
            '--destination=' . $reverseIdentity['destination'], '--manifest=' . $this->fixture['root'] . '/evidence/reverse.jsonl'];
        [$code, $output] = $this->cli('inventory', $reverseArguments, $environment);
        self::assertSame(0, $code, $output);
        $reverse = Manifest::load($this->fixture['root'] . '/evidence/reverse.jsonl', $reverseIdentity);
        [$code, $output] = $this->cli('pause', $reverseArguments, $environment);
        self::assertSame(0, $code, $output);
        [$code, $output] = $this->cli('reverse-copy', [...$reverseArguments, '--review-manifest=' . $reverse->reviewIdentity()], $environment);
        self::assertSame(0, $code, $output);
        self::assertSame("%PDF-1.4\nnew submission\n", file_get_contents($this->fixture['source'] . '/' . $new));
        self::assertSame('uploads/students/cvs/new-submission.pdf', $database->query('SELECT cv_path FROM students')->fetchColumn());
        self::assertStringNotContainsString('synthetic-secret-token', $output);
        unlink($this->fixture['destination'] . '/' . $new);
        [$code] = $this->cli('reverse-copy', [...$reverseArguments, '--review-manifest=' . $reverse->reviewIdentity()], $environment);
        self::assertSame(3, $code);
        self::assertSame("%PDF-1.4\nnew submission\n", file_get_contents($this->fixture['source'] . '/' . $new));
    }

    private function cli(string $operation, array $arguments, array $environment): array
    {
        $process = proc_open([PHP_BINARY, 'scripts/storage-migrate.php', $operation, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3), $environment);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public function testRemovedSourcesRemainInEvidenceAndReviewedDispositionsAllowCompleteVerification(): void
    {
        $manifest = $this->inventory()->scan();
        $manifest->save($this->fixture['manifest']);
        $copier = new Copier(new LocalStorage($this->fixture['source']), new LocalStorage($this->fixture['destination']), $this->identity, $this->inventory());
        $copier->copy($manifest);
        $key = 'uploads/unknown/page-0.bin';
        unlink($this->fixture['source'] . '/' . $key);
        self::assertContains('removed-source', array_column($copier->verify($manifest), 'type'));
        $current = Manifest::load($this->fixture['manifest'], $this->identity);
        self::assertArrayHasKey($key, $current->files);
        self::assertContains('removed', array_column($current->history, 'type'));
        $dispositions = [];
        foreach ($current->issues as $issue) {
            $dispositions[] = ['type' => 'issue', 'key' => $issue['key'], 'issue_type' => $issue['type'], 'decision' => 'Reviewed synthetic exception, preserve source and backup.'];
        }
        $dispositions[] = ['type' => 'file', 'source_key' => $key, 'expected_sha256' => hash('sha256', 'page 0'), 'decision' => 'Retained historical destination, removed from current source.'];
        $current->locked(function (Manifest $manifest) use ($dispositions) {
            $manifest->dispose($dispositions, hash('sha256', json_encode($dispositions, JSON_THROW_ON_ERROR)));
            $manifest->checkpoint();
        });
        self::assertSame([], $copier->verify($current));
        self::assertSame('page 0', file_get_contents($this->fixture['destination'] . '/' . $key));
    }

    public function testSupportedSessionFormatsAreReadWithoutStartingOrChangingSessions(): void
    {
        $session = ['pending_registration' => ['db_data' => ['cv_path' => 'uploads/students/pending/old.pdf']], 'token' => 'sensitive|token;'];
        $before = $_SESSION;
        $status = session_status();
        foreach (['php_serialize', 'php_binary'] as $serializer) {
            $bytes = serialize($session);
            if ($serializer === 'php_binary') {
                $bytes = '';
                foreach ($session as $key => $value) {
                    $bytes .= chr(strlen($key)) . $key . serialize($value);
                }
            }
            file_put_contents($this->fixture['sessions'] . '/sess_synthetic-secret-token', $bytes);
            $identity = $this->identity;
            $identity['serializer'] = $serializer;
            $manifest = (new Inventory(new LocalStorage($this->fixture['source']), $identity, $this->fixture['source']))->scan();
            self::assertSame([], (new ReferenceAudit(makeTestDb(), new LocalStorage($this->fixture['source']), $identity, $this->fixture['sessions'], $serializer))->audit($manifest));
            self::assertCount(1, $manifest->references);
            self::assertStringNotContainsString('sensitive', json_encode($manifest->references));
        }
        self::assertSame($before, $_SESSION);
        self::assertSame($status, session_status());
    }

    public function testReadOnlyReferenceAuditReportsMissingFullKeyWithoutCreatingStorageFiles(): void
    {
        $database = makeTestDb();
        $database->exec("INSERT INTO students (cv_path) VALUES ('uploads/students/cvs/missing.pdf')");
        $manifest = $this->inventory()->scan();
        $differences = (new ReferenceAudit($database, new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['sessions'], 'php'))->audit($manifest);
        self::assertSame('missing', $differences[0]['state']);
        self::assertDirectoryDoesNotExist($this->fixture['source'] . '/storage');
    }

    public function testReferenceAuditChecksCurrentPresenceBeyondTheSavedInventory(): void
    {
        $database = makeTestDb();
        $database->exec("INSERT INTO students (cv_path) VALUES ('uploads/students/cvs/shared.pdf')");
        $manifest = $this->inventory()->scan();
        unlink($this->fixture['source'] . '/uploads/students/cvs/shared.pdf');
        $audit = new ReferenceAudit($database, new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['sessions'], 'php');
        self::assertSame('missing', $audit->audit($manifest)[0]['state']);
        $destinationAudit = new ReferenceAudit($database, new LocalStorage($this->fixture['destination']), $this->identity, $this->fixture['sessions'], 'php', 'destination');
        self::assertSame(['missing', 'missing'], array_column($destinationAudit->audit($manifest), 'state'));
        self::assertDirectoryDoesNotExist($this->fixture['source'] . '/storage');
        self::assertDirectoryDoesNotExist($this->fixture['destination'] . '/storage');
    }

    public function testRawInventoryRecordsInvalidFilenameBytesAndFifoWithoutFollowingThem(): void
    {
        $key = "uploads/unknown/raw-\xff.bin";
        $binary = @file_put_contents($this->fixture['source'] . '/' . $key, 'retained invalid filename') !== false;
        if (!$binary) {
            // macOS filesystems reject invalid UTF-8 before the inventory can see it.
            $key = 'uploads/unknown/back\\slash.bin';
            file_put_contents($this->fixture['source'] . '/' . $key, 'retained invalid filename');
        }
        posix_mkfifo($this->fixture['source'] . '/uploads/unknown/pipe', 0600);
        $manifest = $this->inventory()->scan();
        $manifest->save($this->fixture['manifest']);
        $current = Manifest::load($this->fixture['manifest'], $this->identity);
        $invalid = array_values(array_filter($current->files, static fn ($file) => !$file['valid_key']));
        self::assertCount(1, $invalid);
        self::assertSame($binary ? 'base64' : 'utf8', $invalid[0]['source_key_encoding']);
        self::assertSame($key, $binary ? base64_decode($invalid[0]['source_key'], true) : $invalid[0]['source_key']);
        self::assertContains('nonregular', array_column($current->issues, 'type'));
        self::assertSame('retained invalid filename', file_get_contents($this->fixture['source'] . '/' . $key));
    }

    public function testManifestHasOneWriterAndDeniedSourceReadsNeverBecomeMissing(): void
    {
        $manifest = $this->inventory()->scan();
        $manifest->save($this->fixture['manifest']);
        $lock = fopen($this->fixture['manifest'] . '.lock', 'r+b');
        flock($lock, LOCK_EX);
        try {
            (new Copier(new LocalStorage($this->fixture['source']), new LocalStorage($this->fixture['destination']), $this->identity, $this->inventory()))->copy($manifest);
            self::fail('The second writer must fail before copying.');
        } catch (StorageException $error) {
            self::assertStringContainsString('Another migration writer', $error->getMessage());
            self::assertDirectoryDoesNotExist($this->fixture['destination'] . '/uploads');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        $path = $this->fixture['source'] . '/uploads/students/cvs/shared.pdf';
        chmod($path, 0000);
        try {
            $this->inventory()->scan();
            self::fail('Denied reads cannot become missing content.');
        } catch (StorageException $error) {
            self::assertStringContainsString('read', $error->getMessage());
        } finally {
            chmod($path, 0600);
        }
    }

    public function testOverlappingLocalSourceAndDestinationTreesFailBeforeMutation(): void
    {
        $root = $this->fixture['source'] . '/uploads/recovery';
        mkdir($root, 0700);
        $destination = new LocalStorage($root);
        $identity = $this->identity;
        $identity['destination'] = $destination->migrationIdentity();
        $source = new LocalStorage($this->fixture['source']);
        $inventory = new Inventory($source, $identity, $this->fixture['source']);
        $this->expectException(StorageException::class);
        new Copier($source, $destination, $identity, $inventory);
    }

    public function testOneS3NamespaceCannotBecomeAnIndependentCopyThroughConfigurationChanges(): void
    {
        $settings = ['version' => '2006-03-01', 'endpoint' => 'http://127.0.0.1:1', 'region' => 'us-east-1',
            'use_path_style_endpoint' => true, 'credentials' => ['key' => 'synthetic', 'secret' => 'synthetic']];
        $source = new \Amea\Storage\S3Storage(new \Aws\S3\S3Client($settings), 'synthetic-bucket');
        $settings['region'] = 'auto';
        $settings['use_path_style_endpoint'] = false;
        $destination = new \Amea\Storage\S3Storage(new \Aws\S3\S3Client($settings), 'synthetic-bucket');
        $identity = $this->identity;
        $identity['source'] = $source->migrationIdentity();
        $identity['destination'] = $destination->migrationIdentity();
        self::assertNotSame($identity['source'], $identity['destination']);
        $this->expectException(StorageException::class);
        new Copier($source, $destination, $identity, new Inventory($source, $identity));
    }

    public function testMalformedAndObjectSessionsFailWithoutExportingPrivateValues(): void
    {
        foreach (['pending_registration|O:8:"stdClass":0:{}', 'token|s:999:"synthetic-secret-token";'] as $bytes) {
            file_put_contents($this->fixture['sessions'] . '/sess_synthetic-secret-token', $bytes);
            try {
                (new ReferenceAudit(makeTestDb(), new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['sessions'], 'php'))->audit($this->inventory()->scan());
                self::fail('Unsupported or malformed session records require explicit review.');
            } catch (StorageException $error) {
                self::assertStringNotContainsString('synthetic-secret-token', $error->getMessage());
            }
        }
    }

    public function testLateDatabaseFailureRollsBackAllCorrectionsAndReleasesMigrationLock(): void
    {
        if (getenv('MIGRATION_TEST_DB') !== '1') {
            self::markTestSkipped('Select the dedicated migrated MariaDB fixture.');
        }
        $runtime = \Amea\Migration\MigrationRuntime::fromEnvironment();
        $db = $runtime->connection()->getNativeConnection();
        $this->identity['database'] = ReferenceAudit::databaseIdentity($db);
        $ids = [];
        for ($index = 0; $index < 2; $index++) {
            $db->exec("INSERT INTO students (last_name, first_name, gender, status, identity_document) VALUES ('Synthetic', 'Migration', 'Masculin', 'ETUDIANT', 'shared.pdf')");
            $ids[] = (string) $db->lastInsertId();
        }
        $trigger = 'storage04_' . bin2hex(random_bytes(5));
        $db->exec("CREATE TRIGGER $trigger BEFORE UPDATE ON students FOR EACH ROW BEGIN IF OLD.id = " . (int) $ids[1] . " THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic correction failure'; END IF; END");
        try {
            $manifest = $this->inventory()->scan();
            $audit = new ReferenceAudit($db, new LocalStorage($this->fixture['source']), $this->identity, $this->fixture['sessions'], 'php');
            $audit->audit($manifest);
            $manifest->save($this->fixture['manifest']);
            $destination = new LocalStorage($this->fixture['destination']);
            (new Copier(new LocalStorage($this->fixture['source']), $destination, $this->identity, $this->inventory()))->copy($manifest);
            $mappings = array_map(static fn ($id) => ['table' => 'students', 'record' => $id, 'column' => 'identity_document',
                'expected_old' => 'shared.pdf', 'new_value' => 'uploads/students/identity/shared.pdf'], $ids);
            try {
                $audit->correct($manifest, $mappings, hash('sha256', json_encode($mappings, JSON_THROW_ON_ERROR)), $destination, $runtime);
                self::fail('The injected database error must roll back the entire operation.');
            } catch (\PDOException) {
                $audit->audit($manifest);
                $rows = array_values(array_filter($manifest->references, static fn ($row) => $row['table'] === 'students' && in_array($row['record'], $ids, true)));
                self::assertSame(['shared.pdf', 'shared.pdf'], array_column($rows, 'original_value'));
                self::assertFalse($db->inTransaction());
            }
            $database = $db->query('SELECT IF(@@lower_case_table_names = 0, DATABASE(), LOWER(DATABASE()))')->fetchColumn();
            $lockName = 'amea:migrations:' . substr(hash('sha256', $database), 0, 48);
            $other = new \PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
            $query = $other->prepare('SELECT GET_LOCK(?, 0)');
            $query->execute([$lockName]);
            self::assertSame(1, (int) $query->fetchColumn());
            $other->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        } finally {
            $db->exec('DROP TRIGGER IF EXISTS ' . $trigger);
            $db->exec('DELETE FROM students WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
        }
    }

    public function testWrongTargetsCheckpointsAndChangedDestinationBytesFailClosed(): void
    {
        $manifest = $this->inventory()->scan();
        $manifest->save($this->fixture['manifest']);
        $wrong = $this->identity;
        $wrong['database'] = 'another-database';
        try {
            Manifest::load($this->fixture['manifest'], $wrong);
            self::fail('Wrong database must fail.');
        } catch (StorageException) {
            self::assertDirectoryDoesNotExist($this->fixture['destination'] . '/uploads');
        }
        $copier = new Copier(new LocalStorage($this->fixture['source']), new LocalStorage($this->fixture['destination']), $this->identity, $this->inventory());
        $copier->copy($manifest);
        file_put_contents($this->fixture['destination'] . '/uploads/unknown/page-0.bin', 'change');
        self::assertContains('unverified-destination', array_column($copier->verify($manifest), 'type'));
        file_put_contents($this->fixture['manifest'] . '.checkpoint', '{"incomplete":');
        try {
            Manifest::load($this->fixture['manifest'], $this->identity);
            self::fail('A torn checkpoint cannot be ignored.');
        } catch (\JsonException) {
            self::assertSame('page 0', file_get_contents($this->fixture['source'] . '/uploads/unknown/page-0.bin'));
        }
    }

    public function testRealS3PagesAndReverseCopyIncludeCurrentPostCutoverSubmission(): void
    {
        if (!getenv('STORAGE_TEST_ENDPOINT')) {
            self::markTestSkipped('Select a disposable S3 endpoint with STORAGE_TEST_ENDPOINT.');
        }
        $client = new \Aws\S3\S3Client(['version' => '2006-03-01', 'region' => 'us-east-1',
            'endpoint' => getenv('STORAGE_TEST_ENDPOINT'), 'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'],
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required']);
        $bucket = 'amea-migration-' . bin2hex(random_bytes(8));
        $client->createBucket(['Bucket' => $bucket]);
        $remote = new \Amea\Storage\S3Storage($client, $bucket);
        try {
            $this->identity['destination'] = $remote->migrationIdentity();
            $manifest = $this->inventory()->scan();
            $manifest->save($this->fixture['manifest']);
            (new Copier(new LocalStorage($this->fixture['source']), $remote, $this->identity, $this->inventory()))->copy($manifest);
            $databasePath = $this->fixture['root'] . '/fixture.sqlite';
            makeTestDb()->exec('VACUUM INTO ' . (new \PDO('sqlite::memory:'))->quote($databasePath));
            $cliIdentity = $this->identity;
            $cliIdentity['database'] = ReferenceAudit::databaseIdentity(new \PDO('sqlite:' . $databasePath));
            $environment = array_merge(getenv(), ['MIGRATION_ENVIRONMENT' => 'synthetic', 'MIGRATION_DB_DSN' => 'sqlite:' . $databasePath,
                'MIGRATION_SOURCE_DRIVER' => 'local', 'MIGRATION_SOURCE_ROOT' => $this->fixture['source'],
                'MIGRATION_DESTINATION_DRIVER' => 's3', 'MIGRATION_DESTINATION_S3_ENDPOINT' => getenv('STORAGE_TEST_ENDPOINT'),
                'MIGRATION_DESTINATION_S3_REGION' => 'us-east-1', 'MIGRATION_DESTINATION_S3_BUCKET' => $bucket,
                'MIGRATION_DESTINATION_S3_ACCESS_KEY_ID' => 'storage-test', 'MIGRATION_DESTINATION_S3_SECRET_ACCESS_KEY' => 'disposable-storage-secret',
                'MIGRATION_DESTINATION_S3_PATH_STYLE' => 'true', 'MIGRATION_SESSION_PATH' => $this->fixture['sessions'], 'MIGRATION_SESSION_SERIALIZER' => 'php']);
            $cliPath = $this->fixture['root'] . '/evidence/cli-s3.jsonl';
            $arguments = ['--environment=synthetic', '--database=' . $cliIdentity['database'], '--source=' . $cliIdentity['source'],
                '--destination=' . $cliIdentity['destination'], '--manifest=' . $cliPath];
            [$code, $output] = $this->cli('inventory', $arguments, $environment);
            self::assertSame(0, $code, $output);
            $cliManifest = Manifest::load($cliPath, $cliIdentity);
            [$code, $output] = $this->cli('copy', [...$arguments, '--review-manifest=' . $cliManifest->reviewIdentity()], $environment);
            self::assertSame(0, $code, $output);
            $new = 'uploads/students/cvs/new-post-cutover.pdf';
            $input = fopen('php://temp', 'w+b');
            fwrite($input, "%PDF-1.4\npost cutover\n");
            rewind($input);
            $remote->write($new, $input, 'application/pdf', 1700000000);
            fclose($input);
            $reverseIdentity = $this->identity;
            $reverseIdentity['source'] = $remote->migrationIdentity();
            $reverseIdentity['destination'] = (new LocalStorage($this->fixture['source']))->migrationIdentity();
            $reverseInventory = new Inventory($remote, $reverseIdentity, null, 2);
            $reverse = $reverseInventory->scan();
            self::assertCount(13, $reverse->files);
            $database = makeTestDb();
            $database->prepare('INSERT INTO students (cv_path) VALUES (?)')->execute([$new]);
            self::assertSame([], (new ReferenceAudit($database, $remote, $reverseIdentity, $this->fixture['sessions'], 'php'))->audit($reverse));
            $reverse->save($this->fixture['root'] . '/evidence/reverse.jsonl');
            $copier = new Copier($remote, new LocalStorage($this->fixture['source']), $reverseIdentity, $reverseInventory);
            $copier->copy($reverse);
            self::assertSame([], $copier->verify($reverse));
            self::assertSame("%PDF-1.4\npost cutover\n", file_get_contents($this->fixture['source'] . '/' . $new));
            $failing = new class($client, $bucket) extends \Amea\Storage\S3Storage {
                public function listPage(string $prefix, ?string $cursor, int $limit): array
                {
                    if ($cursor !== null) {
                        throw new StorageException('Synthetic external listing failure on page two.');
                    }
                    return parent::listPage($prefix, $cursor, $limit);
                }
            };
            try {
                (new Inventory($failing, $reverseIdentity, null, 2))->scan();
                self::fail('An incomplete listing cannot produce an inventory.');
            } catch (StorageException $error) {
                self::assertStringContainsString('page two', $error->getMessage());
            }
        } finally {
            foreach ($client->getPaginator('ListObjectsV2', ['Bucket' => $bucket]) as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $client->deleteObject(['Bucket' => $bucket, 'Key' => $object['Key']]);
                }
            }
            $client->deleteBucket(['Bucket' => $bucket]);
        }
    }
}
