<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Core\FileWriteGate;
use Amea\Repository\FileOperationRepository;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\S3Storage;
use Amea\Storage\StorageFactory;
use Aws\S3\S3Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceIntentDrainTest extends TestCase
{
    private string $root;
    private S3Client $client;
    private array $buckets = [];
    private \PDO $db;
    private array $environment;
    private array $arguments;
    private S3Storage $source;
    private string $sourceOwner;
    private string $applicationOwner;
    private string $operationPrefix;

    protected function setUp(): void
    {
        $endpoint = getenv('STORAGE_TEST_ENDPOINT');
        if (!$endpoint) {
            self::markTestSkipped('Set STORAGE_TEST_ENDPOINT to a disposable S3 service.');
        }
        $this->root = sys_get_temp_dir() . '/amea-source-drain-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->root = realpath($this->root);
        foreach (['destination', 'sessions', 'evidence'] as $directory) {
            mkdir($this->root . '/' . $directory, 0700);
        }
        $this->client = new S3Client([
            'version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'],
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
        ]);
        foreach (['source', 'application'] as $role) {
            $bucket = 'amea-drain-' . $role . '-' . bin2hex(random_bytes(8));
            $this->client->createBucket(['Bucket' => $bucket]);
            $this->buckets[$role] = $bucket;
        }
        $this->source = new S3Storage($this->client, $this->buckets['source']);
        makeTestDb()->exec('VACUUM INTO ' . (new \PDO('sqlite::memory:'))->quote($this->root . '/db.sqlite'));
        $this->db = new \PDO('sqlite:' . $this->root . '/db.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->operationPrefix = 'source-drain-' . bin2hex(random_bytes(8));
        $this->environment = [
            'PATH' => (string)getenv('PATH'),
            'MIGRATION_ENVIRONMENT' => 'synthetic-source-drain',
            'MIGRATION_DB_DSN' => 'sqlite:' . $this->root . '/db.sqlite',
            'MIGRATION_SOURCE_DRIVER' => 's3', 'MIGRATION_SOURCE_S3_ENDPOINT' => $endpoint,
            'MIGRATION_SOURCE_S3_REGION' => 'us-east-1', 'MIGRATION_SOURCE_S3_BUCKET' => $this->buckets['source'],
            'MIGRATION_SOURCE_S3_ACCESS_KEY_ID' => 'storage-test',
            'MIGRATION_SOURCE_S3_SECRET_ACCESS_KEY' => 'disposable-storage-secret', 'MIGRATION_SOURCE_S3_PATH_STYLE' => 'true',
            'MIGRATION_DESTINATION_DRIVER' => 'local', 'MIGRATION_DESTINATION_ROOT' => $this->root . '/destination',
            'MIGRATION_SESSION_PATH' => $this->root . '/sessions', 'MIGRATION_SESSION_SERIALIZER' => 'php',
            'FILE_WRITE_GATE_PATH' => $this->root . '/gate',
            'STORAGE_DRIVER' => 's3', 'R2_ENDPOINT' => $endpoint, 'R2_BUCKET_NAME' => $this->buckets['application'],
            'R2_ACCESS_KEY_ID' => 'unrelated-application-key', 'R2_SECRET_ACCESS_KEY' => 'unrelated-application-secret',
        ];
        $environment = $_ENV;
        try {
            $_ENV = ['STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => $endpoint, 'S3_REGION' => 'us-east-1',
                'S3_BUCKET' => $this->buckets['source'], 'S3_ACCESS_KEY_ID' => 'storage-test',
                'S3_SECRET_ACCESS_KEY' => 'disposable-storage-secret'];
            $this->sourceOwner = StorageFactory::identity($this->root);
            $_ENV['S3_BUCKET'] = $this->buckets['application'];
            $this->applicationOwner = StorageFactory::identity($this->root);
        } finally {
            $_ENV = $environment;
        }
        $identity = ['environment' => 'synthetic-source-drain', 'database' => ReferenceAudit::databaseIdentity($this->db),
            'source' => $this->source->migrationIdentity(),
            'destination' => (new LocalStorage($this->root . '/destination'))->migrationIdentity(),
            'sessions' => $this->root . '/sessions', 'serializer' => 'php'];
        $this->arguments = ['--environment=' . $identity['environment'], '--database=' . $identity['database'],
            '--source=' . $identity['source'], '--destination=' . $identity['destination'],
            '--manifest=' . $this->root . '/evidence/manifest.jsonl'];
        (new FileWriteGate($this->root . '/gate'))->pause($identity);
    }

    protected function tearDown(): void
    {
        foreach ($this->buckets as $bucket) {
            foreach ($this->client->getPaginator('ListObjectsV2', ['Bucket' => $bucket]) as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $this->client->deleteObject(['Bucket' => $bucket, 'Key' => $object['Key']]);
                }
            }
            $this->client->deleteBucket(['Bucket' => $bucket]);
        }
        if (isset($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
    }

    private function drain(array $overrides = []): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/scripts/storage-migrate.php', 'drain-intents', ...$this->arguments],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null,
            array_replace($this->environment, $overrides));
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }

    public function testActualCliDrainsOnlyExplicitSourceOwnedIntentsWithAnR2ApplicationEnvironment(): void
    {
        $key = 'uploads/students/pending/source-cleanup.pdf';
        $operations = new FileOperationRepository($this->db);
        foreach ($this->buckets as $bucket) {
            $this->client->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => 'synthetic retained bytes']);
        }
        file_put_contents($this->root . '/destination/sentinel.txt', 'retained destination');
        $this->db->beginTransaction();
        $operations->enqueue($this->operationPrefix . '-source', $this->sourceOwner, 'replacement', [$key]);
        $operations->enqueue($this->operationPrefix . '-application', $this->applicationOwner, 'replacement', [$key]);
        $this->db->commit();
        $applicationIntent = $operations->find($this->operationPrefix . '-application');
        self::assertNotSame($this->sourceOwner, $this->applicationOwner);
        [$exit, $output] = $this->drain();
        self::assertSame(1, $exit, $output);
        self::assertStringContainsString('Pending intents remain.', $output);
        self::assertNull($operations->find($this->operationPrefix . '-source'));
        self::assertNull($this->source->stat($key));
        self::assertSame($applicationIntent, $operations->find($this->operationPrefix . '-application'));
        self::assertSame('synthetic retained bytes', (string)$this->client->getObject(['Bucket' => $this->buckets['application'], 'Key' => $key])['Body']);
        self::assertSame('retained destination', file_get_contents($this->root . '/destination/sentinel.txt'));
        $operations->remove($this->operationPrefix . '-application');
        [$exit, $output] = $this->drain();
        self::assertSame(0, $exit, $output);
        self::assertTrue((new FileWriteGate($this->root . '/gate'))->status()['paused']);
    }

    public function testAbsentExplicitSourceFieldsCannotReplayApplicationIntentsThroughR2Aliases(): void
    {
        $key = 'uploads/students/pending/retain-without-source.pdf';
        foreach ($this->buckets as $bucket) {
            $this->client->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => 'retained bytes']);
        }
        file_put_contents($this->root . '/destination/sentinel.txt', 'retained destination');
        $operations = new FileOperationRepository($this->db);
        $this->db->beginTransaction();
        $operations->enqueue($this->operationPrefix, $this->applicationOwner, 'replacement', [$key]);
        $this->db->commit();
        $intent = $operations->find($this->operationPrefix);
        // Even matching operator selections must not authorize implicit application fallback.
        $application = new S3Storage(new S3Client([
            'version' => '2006-03-01', 'region' => 'auto', 'endpoint' => $this->environment['R2_ENDPOINT'],
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'],
        ]), $this->buckets['application']);
        $selection = (new FileWriteGate($this->root . '/gate'))->status()['selection'];
        $selection['source'] = $application->migrationIdentity();
        $this->environment['FILE_WRITE_GATE_PATH'] = $this->root . '/application-gate';
        (new FileWriteGate($this->environment['FILE_WRITE_GATE_PATH']))->pause($selection);
        $this->arguments[2] = '--source=' . $selection['source'];
        $overrides = ['R2_ACCESS_KEY_ID' => 'storage-test', 'R2_SECRET_ACCESS_KEY' => 'disposable-storage-secret'];
        foreach (['ENDPOINT', 'REGION', 'BUCKET', 'ACCESS_KEY_ID', 'SECRET_ACCESS_KEY'] as $name) {
            unset($this->environment['MIGRATION_SOURCE_S3_' . $name]);
        }
        [$exit, $output] = $this->drain($overrides);
        self::assertSame(1, $exit, $output);
        self::assertStringContainsString('requires MIGRATION_SOURCE_S3_ENDPOINT', $output);
        self::assertSame($intent, $operations->find($this->operationPrefix));
        foreach ($this->buckets as $bucket) {
            self::assertSame('retained bytes', (string)$this->client->getObject(['Bucket' => $bucket, 'Key' => $key])['Body']);
        }
        self::assertSame('retained destination', file_get_contents($this->root . '/destination/sentinel.txt'));
        self::assertTrue((new FileWriteGate($this->environment['FILE_WRITE_GATE_PATH']))->status()['paused']);
    }

    public static function invalidSourceConfigurations(): array
    {
        $cases = [];
        foreach (['ENDPOINT', 'REGION', 'BUCKET', 'ACCESS_KEY_ID', 'SECRET_ACCESS_KEY'] as $name) {
            $cases['missing ' . $name] = [['MIGRATION_SOURCE_S3_' . $name => ''], 'requires MIGRATION_SOURCE_S3_' . $name];
        }
        return $cases + [
            'all empty' => [array_fill_keys(array_map(static fn (string $name): string => 'MIGRATION_SOURCE_S3_' . $name,
                ['ENDPOINT', 'REGION', 'BUCKET', 'ACCESS_KEY_ID', 'SECRET_ACCESS_KEY']), ''), 'requires MIGRATION_SOURCE_S3_ENDPOINT'],
            'blank credential' => [['MIGRATION_SOURCE_S3_SECRET_ACCESS_KEY' => '  '], 'requires MIGRATION_SOURCE_S3_SECRET_ACCESS_KEY'],
            'invalid endpoint' => [['MIGRATION_SOURCE_S3_ENDPOINT' => 'ftp://invalid.example'], 'S3_ENDPOINT must be an HTTP(S) endpoint'],
            'invalid region' => [['MIGRATION_SOURCE_S3_REGION' => 'INVALID REGION'], 'Invalid S3 bucket or region'],
            'invalid bucket' => [['MIGRATION_SOURCE_S3_BUCKET' => 'INVALID BUCKET'], 'Invalid S3 bucket or region'],
            'invalid path style' => [['MIGRATION_SOURCE_S3_PATH_STYLE' => 'invalid'], 'S3_PATH_STYLE must be true, false, 1 or 0'],
        ];
    }

    #[DataProvider('invalidSourceConfigurations')]
    public function testInvalidExplicitSourceConfigurationFailsBeforeAnyIntentReplay(array $overrides, string $message): void
    {
        $key = 'uploads/students/pending/retain-with-invalid-source.pdf';
        $operations = new FileOperationRepository($this->db);
        $this->db->beginTransaction();
        $operations->enqueue($this->operationPrefix . '-source', $this->sourceOwner, 'replacement', [$key]);
        $operations->enqueue($this->operationPrefix . '-application', $this->applicationOwner, 'replacement', [$key]);
        $this->db->commit();
        $sourceIntent = $operations->find($this->operationPrefix . '-source');
        $applicationIntent = $operations->find($this->operationPrefix . '-application');
        foreach ($this->buckets as $bucket) {
            $this->client->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => 'retained bytes']);
        }
        file_put_contents($this->root . '/destination/sentinel.txt', 'retained destination');
        [$exit, $output] = $this->drain($overrides);
        self::assertSame(1, $exit, $output);
        self::assertStringContainsString($message, $output);
        self::assertSame($sourceIntent, $operations->find($this->operationPrefix . '-source'));
        self::assertSame($applicationIntent, $operations->find($this->operationPrefix . '-application'));
        foreach ($this->buckets as $bucket) {
            self::assertSame('retained bytes', (string)$this->client->getObject(['Bucket' => $bucket, 'Key' => $key])['Body']);
        }
        self::assertSame('retained destination', file_get_contents($this->root . '/destination/sentinel.txt'));
        self::assertTrue((new FileWriteGate($this->root . '/gate'))->status()['paused']);
    }
}
