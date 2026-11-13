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
        foreach (['STORAGE_DRIVER', 'S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY', 'S3_PATH_STYLE'] as $key) {
            $_ENV[$key] = '';
        }
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
            foreach (['STORAGE_DRIVER', 'S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY', 'S3_PATH_STYLE'] as $key) {
                self::assertSame(1, substr_count($compose, '      ' . $key . ':'), $file . ' must define shared ' . $key);
            }
            self::assertSame(2, substr_count($compose, '<<: *shared_environment'));
        }
    }
}
