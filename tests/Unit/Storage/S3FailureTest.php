<?php

namespace Amea\Tests\Unit\Storage;

use Amea\Storage\S3Storage;
use Amea\Storage\StorageException;
use Aws\Command;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
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
    public static function incompleteCopyResponses(): array
    {
        return [
            'truncated new destination' => [true, false],
            'truncated existing destination' => [true, true],
            'wrong digest new destination' => [false, false],
            'wrong digest existing destination' => [false, true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('incompleteCopyResponses')]
    public function testInvalidCopyContentFailsBeforeDestinationPublication(bool $truncated, bool $destinationExists): void
    {
        $original = 'original content';
        $objects = ['uploads/source' => $original];
        if ($destinationExists) {
            $objects['uploads/destination'] = 'existing destination';
        }
        $sourceRequested = false;
        $putAccepted = false;
        $boundary = function (CommandInterface $command) use (&$objects, &$sourceRequested, &$putAccepted, $original, $truncated) {
            $key = $command['Key'];
            if ($command->getName() === 'PutObject') {
                $putAccepted = true;
                $objects[$key] = (string) $command['Body'];
                return new Result([]);
            }
            if (!isset($objects[$key])) {
                return $this->error(404, 'NoSuchKey');
            }
            $content = $objects[$key];
            $metadata = ['created-at' => '1234', 'sha256' => hash('sha256', $content)];
            if ($key === 'uploads/source' && !$sourceRequested) {
                $sourceRequested = true;
                $content = $truncated ? 'partial' : str_repeat('x', strlen($original));
                if ($truncated) {
                    unset($metadata['sha256']);
                }
            }
            return new Result([
                'Body' => Utils::streamFor($content), 'ContentLength' => strlen($objects[$key]),
                'ContentType' => 'text/plain', 'Metadata' => $metadata,
            ]);
        };
        $storage = $this->storage(array_fill(0, 8, $boundary));
        $failed = false;
        try {
            $storage->copy('uploads/source', 'uploads/destination');
        } catch (StorageException) {
            $failed = true;
        }
        self::assertTrue($failed, 'Incomplete or mismatched source responses must fail.');
        self::assertFalse($putAccepted, 'Source verification must finish before destination publication.');
        self::assertSame($original, stream_get_contents($storage->read('uploads/source')));
        if ($destinationExists) {
            self::assertSame('existing destination', stream_get_contents($storage->read('uploads/destination')));
        } else {
            self::assertNull($storage->stat('uploads/destination'));
        }
    }

}
