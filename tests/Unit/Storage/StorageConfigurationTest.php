<?php

namespace Amea\Tests\Unit\Storage;

use Amea\Core\Container;
use Amea\Storage\LocalStorage;
use Amea\Storage\S3Storage;
use Amea\Storage\StorageException;
use Amea\Storage\StorageFactory;
use PHPUnit\Framework\TestCase;

class StorageConfigurationTest extends TestCase
{
    private array $environment;

    protected function setUp(): void
    {
        $this->environment = $_ENV;
        foreach (['STORAGE_DRIVER', 'S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY', 'S3_PATH_STYLE', 'R2_ACCOUNT_ID', 'R2_ENDPOINT', 'R2_BUCKET_NAME', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_ACCESS_TOKEN'] as $key) {
            $_ENV[$key] = '';
        }
        $_ENV['S3_PATH_STYLE'] = 'false';
        Container::reset();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->environment;
        Container::reset();
    }

    public function testLocalDefaultAndContainerInjectionAreResettable(): void
    {
        unset($_ENV['STORAGE_DRIVER']);
        self::assertInstanceOf(LocalStorage::class, StorageFactory::fromEnvironment(dirname(__DIR__, 3)));
        $_ENV['STORAGE_DRIVER'] = 'local';
        $storage = Container::storage();
        self::assertSame($storage, Container::storage());
        $injected = new LocalStorage(dirname(__DIR__, 3));
        Container::setStorage($injected);
        self::assertSame($injected, Container::storage());
        Container::reset();
        self::assertNotSame($injected, Container::storage());
        self::assertNotSame($storage, Container::storage());
    }

    public function testS3ConfigurationSupportsAnExplicitPortableEndpoint(): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => 'https://example.r2.cloudflarestorage.com', 'S3_REGION' => 'auto', 'S3_BUCKET' => 'amea-private', 'S3_ACCESS_KEY_ID' => 'key', 'S3_SECRET_ACCESS_KEY' => 'secret', 'S3_PATH_STYLE' => 'true']);
        self::assertInstanceOf(S3Storage::class, StorageFactory::fromEnvironment(dirname(__DIR__, 3)));
    }

    public function testR2AliasesConfigureTheContainerOnlyAfterExplicitActivation(): void
    {
        $_ENV = array_merge($_ENV, [
            'R2_ENDPOINT' => 'https://0123456789abcdef0123456789abcdef.r2.cloudflarestorage.com',
            'R2_BUCKET_NAME' => 'configured-alias-bucket', 'R2_ACCESS_KEY_ID' => 'alias-key',
            'R2_SECRET_ACCESS_KEY' => 'alias-secret', 'R2_ACCESS_TOKEN' => 'unused-bearer',
            'S3_PATH_STYLE' => 'false',
        ]);
        unset($_ENV['STORAGE_DRIVER']);
        self::assertInstanceOf(LocalStorage::class, Container::storage());
        $_ENV['STORAGE_DRIVER'] = 's3';
        Container::reset();
        self::assertInstanceOf(S3Storage::class, Container::storage());
    }

    public function testR2AccountDerivesEndpointAndDefaultRegionWithExplicitEndpointTakingPriority(): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3',
            'S3_ENDPOINT' => 'https://0123456789abcdef0123456789abcdef.r2.cloudflarestorage.com',
            'S3_REGION' => 'auto', 'S3_BUCKET' => 'configured-bucket',
            'S3_ACCESS_KEY_ID' => 'key', 'S3_SECRET_ACCESS_KEY' => 'secret']);
        $canonical = StorageFactory::fromEnvironment(dirname(__DIR__, 3));
        foreach (['S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY'] as $key) {
            $_ENV[$key] = '';
        }
        $_ENV = array_merge($_ENV, ['R2_ACCOUNT_ID' => '0123456789abcdef0123456789abcdef',
            'R2_BUCKET_NAME' => 'configured-bucket', 'R2_ACCESS_KEY_ID' => 'key', 'R2_SECRET_ACCESS_KEY' => 'secret']);
        self::assertSame($canonical->migrationIdentity(), StorageFactory::fromEnvironment(dirname(__DIR__, 3))->migrationIdentity());
        $_ENV['R2_ENDPOINT'] = 'https://0123456789abcdef0123456789abcdef.r2.cloudflarestorage.com';
        $_ENV['R2_ACCOUNT_ID'] = 'unused-invalid-account';
        self::assertSame($canonical->migrationIdentity(), StorageFactory::fromEnvironment(dirname(__DIR__, 3))->migrationIdentity());
    }

    public function testAliasesPreserveTheExistingApplicationIdentityAndFollowConfiguredBucketChanges(): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => 'https://provider.example',
            'S3_REGION' => 'auto', 'S3_BUCKET' => 'configured-bucket',
            'S3_ACCESS_KEY_ID' => 'key', 'S3_SECRET_ACCESS_KEY' => 'secret']);
        $identity = StorageFactory::identity(dirname(__DIR__, 3));
        self::assertSame('4cfbf3202ef89449551b9f06bdf6a32919803d678a9c2f3c5c2388dcf70b7852', $identity);
        $canonical = StorageFactory::fromEnvironment(dirname(__DIR__, 3));
        foreach (['S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY'] as $key) {
            $_ENV[$key] = '';
        }
        $_ENV = array_merge($_ENV, ['R2_ENDPOINT' => 'https://provider.example',
            'R2_BUCKET_NAME' => 'configured-bucket', 'R2_ACCESS_KEY_ID' => 'rotated-key',
            'R2_SECRET_ACCESS_KEY' => 'rotated-secret']);
        self::assertSame($identity, StorageFactory::identity(dirname(__DIR__, 3)));
        self::assertSame($canonical->migrationIdentity(), StorageFactory::fromEnvironment(dirname(__DIR__, 3))->migrationIdentity());
        $_ENV['R2_BUCKET_NAME'] = 'another-private-bucket';
        self::assertNotSame($identity, StorageFactory::identity(dirname(__DIR__, 3)));
        self::assertNotSame($canonical->migrationIdentity(), StorageFactory::fromEnvironment(dirname(__DIR__, 3))->migrationIdentity());
    }

    public function testCompleteCanonicalConfigurationTakesPriorityOverStaleAliases(): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => 'https://provider.example',
            'S3_REGION' => 'eu-central-1', 'S3_BUCKET' => 'canonical-bucket',
            'S3_ACCESS_KEY_ID' => 'canonical-key', 'S3_SECRET_ACCESS_KEY' => 'canonical-secret']);
        $identity = StorageFactory::identity(dirname(__DIR__, 3));
        $canonical = StorageFactory::fromEnvironment(dirname(__DIR__, 3));
        $_ENV = array_merge($_ENV, ['R2_ENDPOINT' => 'invalid-stale-endpoint',
            'R2_ACCOUNT_ID' => 'invalid-stale-account', 'R2_BUCKET_NAME' => 'invalid stale bucket',
            'R2_ACCESS_KEY_ID' => 'stale-key', 'R2_SECRET_ACCESS_KEY' => 'stale-secret']);
        self::assertSame($identity, StorageFactory::identity(dirname(__DIR__, 3)));
        self::assertSame($canonical->migrationIdentity(), StorageFactory::fromEnvironment(dirname(__DIR__, 3))->migrationIdentity());
    }

    public static function partialCanonicalConfiguration(): array
    {
        return array_map(fn (string $key): array => [$key], [
            'S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('partialCanonicalConfiguration')]
    public function testPartialCanonicalConfigurationCannotBorrowR2Values(string $key): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3',
            'R2_ENDPOINT' => 'https://provider.example', 'R2_BUCKET_NAME' => 'alias-bucket',
            'R2_ACCESS_KEY_ID' => 'alias-key', 'R2_SECRET_ACCESS_KEY' => 'alias-secret']);
        $_ENV[$key] = 'explicit-canonical-value';
        $this->expectException(StorageException::class);
        StorageFactory::fromEnvironment(dirname(__DIR__, 3));
    }

    public static function invalidR2Configuration(): array
    {
        return [
            ['R2_ENDPOINT', 'file:///tmp/s3'], ['R2_ENDPOINT', 'https://user:synthetic-secret@provider.example'],
            ['R2_ENDPOINT', 'https://provider.example?token=synthetic-secret'],
            ['R2_ENDPOINT', 'https://provider.example#synthetic-secret'],
            ['R2_BUCKET_NAME', ''], ['R2_BUCKET_NAME', 'invalid bucket'],
            ['R2_ACCESS_KEY_ID', ''], ['R2_SECRET_ACCESS_KEY', ''],
            ['R2_ACCOUNT_ID', 'account/../../synthetic-secret'], ['R2_ACCOUNT_ID', ''],
            ['S3_PATH_STYLE', 'perhaps'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidR2Configuration')]
    public function testInvalidR2ConfigurationFailsWithoutUsingBearerOrDisclosingValues(string $key, string $value): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3', 'R2_ENDPOINT' => 'https://provider.example',
            'R2_ACCOUNT_ID' => '0123456789abcdef0123456789abcdef',
            'R2_BUCKET_NAME' => 'alias-bucket', 'R2_ACCESS_KEY_ID' => 'synthetic-key',
            'R2_SECRET_ACCESS_KEY' => 'synthetic-secret', 'R2_ACCESS_TOKEN' => 'synthetic-bearer']);
        if ($key === 'R2_ACCOUNT_ID') {
            $_ENV['R2_ENDPOINT'] = '';
        }
        $_ENV[$key] = $value;
        try {
            StorageFactory::fromEnvironment(dirname(__DIR__, 3));
            self::fail('Invalid selected configuration must fail.');
        } catch (StorageException $error) {
            self::assertStringNotContainsString('synthetic-secret', $error->getMessage());
            self::assertStringNotContainsString('synthetic-bearer', $error->getMessage());
            self::assertStringNotContainsString('synthetic-key', $error->getMessage());
        }
    }

    public static function invalidConfiguration(): array
    {
        return [['STORAGE_DRIVER', 'unknown'], ['S3_ENDPOINT', ''], ['S3_ENDPOINT', 'file:///tmp/s3'], ['S3_ENDPOINT', 'https://user:secret@example.com'], ['S3_REGION', ''], ['S3_BUCKET', ''], ['S3_ACCESS_KEY_ID', ''], ['S3_SECRET_ACCESS_KEY', ''], ['S3_PATH_STYLE', 'perhaps']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidConfiguration')]
    public function testInvalidSelectedBackendFailsExplicitly(string $key, string $value): void
    {
        $_ENV = array_merge($_ENV, ['STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => 'http://localhost:9000', 'S3_REGION' => 'us-east-1', 'S3_BUCKET' => 'amea-test', 'S3_ACCESS_KEY_ID' => 'key', 'S3_SECRET_ACCESS_KEY' => 'secret', 'S3_PATH_STYLE' => '1']);
        $_ENV[$key] = $value;
        $this->expectException(StorageException::class);
        StorageFactory::fromEnvironment(dirname(__DIR__, 3));
    }

    public function testComposeAppsAndJobsShareStorageSettings(): void
    {
        foreach (['compose.yml', 'compose.dokploy.yml'] as $file) {
            $compose = file_get_contents(dirname(__DIR__, 3) . '/' . $file);
            foreach (['STORAGE_DRIVER', 'S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY', 'S3_PATH_STYLE', 'R2_ACCOUNT_ID', 'R2_ENDPOINT', 'R2_BUCKET_NAME', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'] as $key) {
                self::assertSame(1, substr_count($compose, '      ' . $key . ':'), $file . ' must define shared ' . $key);
            }
            self::assertSame(2, substr_count($compose, '<<: *shared_environment'));
            self::assertStringNotContainsString('R2_ACCESS_TOKEN:', $compose);
        }
    }
}
