<?php

namespace Amea\Tests\Unit\Storage;

use Amea\Storage\S3Storage;
use Amea\Storage\StorageException;
use Aws\Command;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class S3FailureTest extends TestCase
{
    private function storage(array $responses): S3Storage
    {
        return new S3Storage(new S3Client([
            'version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => 'http://storage.invalid',
            'credentials' => ['key' => 'key', 'secret' => 'secret'], 'retries' => 0,
            'handler' => new MockHandler($responses),
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
        ]), 'amea-test');
    }

    private function error(?int $status, string $code): S3Exception
    {
        return new S3Exception('Injected storage boundary failure', new Command('StorageOperation'), [
            'code' => $code, 'response' => $status === null ? null : new Response($status),
            'connection_error' => $status === null,
        ]);
    }

    public static function failures(): array
    {
        $cases = [];
        foreach (['read', 'write', 'stat', 'delete', 'copy', 'listPage'] as $operation) {
            foreach ([[403, 'AccessDenied'], [500, 'InternalError'], [null, 'ConnectionError'], [404, 'NoSuchBucket']] as [$status, $code]) {
                $cases[] = [$operation, $status, $code];
            }
        }
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('failures')]
    public function testStorageAndTransportFailuresAreExplicit(string $operation, ?int $status, string $code): void
    {
        $storage = $this->storage([$this->error($status, $code)]);
        $source = fopen('php://temp', 'w+b');
        fwrite($source, 'content');
        rewind($source);
        $arguments = match ($operation) {
            'write' => ['uploads/test', $source, 'text/plain', 1],
            'copy' => ['uploads/source', 'uploads/destination'],
            'listPage' => ['', null, 10],
            default => ['uploads/test'],
        };
        try {
            $this->expectException(StorageException::class);
            $storage->$operation(...$arguments);
        } finally {
            fclose($source);
        }
    }

    public function testAmbiguousHead404RequiresAConfirmedExistingBucket(): void
    {
        $storage = $this->storage([$this->error(404, 'NotFound'), $this->error(404, 'NotFound')]);
        $this->expectException(StorageException::class);
        $storage->stat('uploads/missing');
    }

    public function testConfirmedMissingObjectsHaveDistinctStatAndDeleteBehavior(): void
    {
        self::assertNull($this->storage([$this->error(404, 'NoSuchKey')])->stat('uploads/missing'));
        self::assertNull($this->storage([$this->error(404, 'NotFound'), new Result([])])->stat('uploads/missing'));
        $this->storage([$this->error(404, 'NoSuchKey')])->delete('uploads/missing');
        self::assertTrue(true);
    }
}
