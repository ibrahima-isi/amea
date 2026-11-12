<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Storage\S3Storage;
use Amea\Tests\Unit\Storage\StorageContractTest;
use Aws\S3\S3Client;

class S3StorageTest extends StorageContractTest
{
    private S3Client $client;
    private string $bucket;

    protected function setUp(): void
    {
        $endpoint = getenv('STORAGE_TEST_ENDPOINT');
        if (!$endpoint) {
            self::markTestSkipped('Set STORAGE_TEST_ENDPOINT to a disposable S3 service.');
        }
        $this->bucket = 'amea-test-' . bin2hex(random_bytes(8));
        $this->client = new S3Client([
            'version' => '2006-03-01', 'region' => getenv('STORAGE_TEST_REGION') ?: 'us-east-1',
            'endpoint' => $endpoint, 'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'],
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
        ]);
        $this->client->createBucket(['Bucket' => $this->bucket]);
        $this->storage = new S3Storage($this->client, $this->bucket);
    }

    protected function tearDown(): void
    {
        if (isset($this->bucket)) {
            foreach ($this->client->getPaginator('ListObjectsV2', ['Bucket' => $this->bucket]) as $page) {
                foreach ($page['Contents'] ?? [] as $object) {
                    $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $object['Key']]);
                }
            }
            $this->client->deleteBucket(['Bucket' => $this->bucket]);
        }
    }
    public function testFactoryUsesSelectedEndpointBucketAndCredentials(): void
    {
        $environment = $_ENV;
        $_ENV = array_merge($_ENV, [
            'STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => getenv('STORAGE_TEST_ENDPOINT'),
            'S3_REGION' => getenv('STORAGE_TEST_REGION') ?: 'us-east-1', 'S3_BUCKET' => $this->bucket,
            'S3_ACCESS_KEY_ID' => 'storage-test', 'S3_SECRET_ACCESS_KEY' => 'disposable-storage-secret', 'S3_PATH_STYLE' => 'true',
        ]);
        try {
            $storage = \Amea\Storage\StorageFactory::fromEnvironment(sys_get_temp_dir());
            $storage->write('uploads/factory.txt', $this->input('factory endpoint'), 'text/plain', 1234);
            self::assertSame('factory endpoint', stream_get_contents($this->storage->read('uploads/factory.txt')));
            $_ENV['S3_ACCESS_KEY_ID'] = 'denied';
            $denied = \Amea\Storage\StorageFactory::fromEnvironment(sys_get_temp_dir());
            foreach ([fn () => $denied->read('uploads/factory.txt'), fn () => $denied->stat('uploads/missing'), fn () => $denied->delete('uploads/factory.txt'), fn () => $denied->write('uploads/denied', $this->input('x'), 'text/plain', 1), fn () => $denied->copy('uploads/factory.txt', 'uploads/denied'), fn () => $denied->listPage('', null, 10)] as $operation) {
                try {
                    $operation();
                    self::fail('Invalid credentials must fail.');
                } catch (\Amea\Storage\StorageException $error) {
                    self::assertSame(403, $error->getPrevious()->getStatusCode());
                }
            }
            $_ENV['S3_ACCESS_KEY_ID'] = 'storage-test';
            $_ENV['S3_BUCKET'] = 'absent-' . bin2hex(random_bytes(6));
            try {
                \Amea\Storage\StorageFactory::fromEnvironment(sys_get_temp_dir())->stat('uploads/missing');
                self::fail('A missing bucket must fail explicitly.');
            } catch (\Amea\Storage\StorageException) {
                self::assertTrue(true);
            }
            $_ENV['S3_ENDPOINT'] = 'http://127.0.0.1:1';
            $this->expectException(\Amea\Storage\StorageException::class);
            \Amea\Storage\StorageFactory::fromEnvironment(sys_get_temp_dir())->stat('uploads/missing');
        } finally {
            $_ENV = $environment;
        }
    }

    protected function concurrentEnvironment(): array
    {
        return [
            'STORAGE_DRIVER' => 's3', 'S3_ENDPOINT' => getenv('STORAGE_TEST_ENDPOINT'),
            'S3_REGION' => getenv('STORAGE_TEST_REGION') ?: 'us-east-1', 'S3_BUCKET' => $this->bucket,
            'S3_ACCESS_KEY_ID' => 'storage-test', 'S3_SECRET_ACCESS_KEY' => 'disposable-storage-secret', 'S3_PATH_STYLE' => 'true',
        ];
    }
}
