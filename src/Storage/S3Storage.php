<?php

namespace Amea\Storage;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

class S3Storage implements Storage
{
    use ValidatesStorageKeys;

    public function __construct(private S3Client $client, private string $bucket)
    {
    }

    public function read(string $key): mixed
    {
        $this->validateKey($key);
        $result = $this->response($key);
        $stream = $result['Body']->detach();
        if (!is_resource($stream)) {
            throw new StorageException('The S3 response did not contain a readable stream.');
        }
        return $stream;
    }

    public function write(string $key, mixed $source, string $mime, int $createdAt): array
    {
        return $this->writeStream($key, $source, $mime, $createdAt);
    }

    private function writeStream(string $key, mixed $source, string $mime, int $createdAt, ?int $expectedSize = null, ?string $expectedDigest = null): array
    {
        $this->validateKey($key);
        if (!is_resource($source) || get_resource_type($source) !== 'stream' || !strpbrk(stream_get_meta_data($source)['mode'], 'r+')) {
            throw new StorageException('Storage writes require a readable stream.');
        }
        if ($mime === '' || strlen($mime) > 255 || preg_match('/[\x00-\x1f\x7f]/', $mime) || $createdAt < 0) {
            throw new StorageException('Invalid storage content metadata.');
        }
        // Disk spooling also supports non-seekable sources and repeatable SDK retries.
        $temporary = tmpfile();
        if ($temporary === false) {
            throw new StorageException('Unable to allocate the upload spool.');
        }
        try {
            $size = @stream_copy_to_stream($source, $temporary);
            if ($size === false) {
                throw new StorageException('Unable to spool the upload stream.');
            }
            rewind($temporary);
            $fingerprint = $this->fingerprint($temporary, $expectedSize ?? $size, $expectedDigest);
            $digest = $fingerprint['sha256'];
            rewind($temporary);
            try {
                $this->client->putObject([
                    'Bucket' => $this->bucket, 'Key' => $key, 'Body' => $temporary,
                    'ContentLength' => $size, 'ContentType' => $mime, 'IfNoneMatch' => '*',
                    'Metadata' => ['created-at' => (string) $createdAt, 'sha256' => $digest],
                ]);
            } catch (AwsException $error) {
                if ($error->getStatusCode() !== 412) {
                    throw new StorageException('Unable to write the S3 object.', 0, $error);
                }
                $result = $this->response($key);
                $existing = $result['Body']->detach();
                if (!is_resource($existing)) {
                    throw new StorageException('The S3 destination did not contain a readable stream.');
                }
                try {
                    $metadata = $this->metadata($result);
                    $existingFingerprint = $this->fingerprint($existing, $metadata['size'], $metadata['sha256']);
                } finally {
                    fclose($existing);
                }
                if ($existingFingerprint['size'] !== $size || $existingFingerprint['sha256'] !== $digest) {
                    throw new StorageException('The S3 destination contains conflicting content.', 0, $error);
                }
            }
            return ['size' => $size, 'sha256' => $digest];
        } finally {
            fclose($temporary);
        }
    }

    public function stat(string $key): ?array
    {
        $this->validateKey($key);
        try {
            $result = $this->client->headObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (AwsException $error) {
            if ($error->getStatusCode() === 404 && $error->getAwsErrorCode() === 'NoSuchKey') {
                return null;
            }
            if ($error->getStatusCode() === 404 && in_array($error->getAwsErrorCode(), ['NotFound', '404'], true)) {
                try {
                    // HEAD responses have no error body, so confirm the bucket exists.
                    $this->client->headBucket(['Bucket' => $this->bucket]);
                    return null;
                } catch (AwsException $bucketError) {
                    throw new StorageException('Unable to confirm the S3 bucket for a missing object.', 0, $bucketError);
                }
            }
            throw new StorageException('Unable to inspect the S3 object.', 0, $error);
        }
        return $this->metadata($result);
    }

    public function delete(string $key): void
    {
        $this->validateKey($key);
        try {
            $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        } catch (AwsException $error) {
            if ($error->getStatusCode() === 404 && $error->getAwsErrorCode() === 'NoSuchKey') {
                return;
            }
            throw new StorageException('Unable to delete the S3 object.', 0, $error);
        }
    }

    public function copy(string $sourceKey, string $destinationKey): void
    {
        $this->validateKey($sourceKey);
        $this->validateKey($destinationKey);
        // Read metadata and bytes from the same object version.
        $result = $this->response($sourceKey);
        $source = $result['Body']->detach();
        if (!is_resource($source)) {
            throw new StorageException('The S3 copy source did not contain a readable stream.');
        }
        try {
            $metadata = $this->metadata($result);
            // PutObject provides atomic destination protection across S3 providers.
            $this->writeStream($destinationKey, $source, $metadata['mime'], $metadata['createdAt'], $metadata['size'], $metadata['sha256']);
        } finally {
            fclose($source);
        }
    }

    public function listPage(string $prefix, ?string $cursor, int $limit): array
    {
        $prefix = $prefix === '' ? 'uploads/' : $prefix;
        $this->validateKey(str_ends_with($prefix, '/') ? $prefix . 'prefix-check' : $prefix);
        if ($limit < 1 || $limit > 1000) {
            throw new StorageException('The storage page limit must be between 1 and 1000.');
        }
        $request = ['Bucket' => $this->bucket, 'Prefix' => $prefix, 'MaxKeys' => $limit];
        if ($cursor !== null) {
            $decoded = base64_decode($cursor, true);
            $page = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($page) || ($page['prefix'] ?? null) !== $prefix || !is_string($page['token'] ?? null) || $page['token'] === '') {
                throw new StorageException('Invalid storage cursor.');
            }
            $request['ContinuationToken'] = $page['token'];
        }
        try {
            $result = $this->client->listObjectsV2($request);
            $objects = [];
            foreach ($result['Contents'] ?? [] as $object) {
                $metadata = $this->stat($object['Key']);
                if ($metadata === null) {
                    throw new StorageException('An object disappeared during the storage listing.');
                }
                $objects[] = ['key' => $object['Key'], 'size' => $metadata['size'], 'createdAt' => $metadata['createdAt']];
            }
            $next = null;
            if (!empty($result['IsTruncated'])) {
                if (empty($result['NextContinuationToken'])) {
                    throw new StorageException('The S3 listing omitted its continuation token.');
                }
                $next = base64_encode(json_encode(['prefix' => $prefix, 'token' => $result['NextContinuationToken']], JSON_THROW_ON_ERROR));
            }
            return ['objects' => $objects, 'cursor' => $next];
        } catch (AwsException $error) {
            throw new StorageException('Unable to list S3 objects.', 0, $error);
        }
    }

    private function response(string $key): \Aws\ResultInterface
    {
        try {
            return $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $key, '@http' => ['stream' => true]]);
        } catch (AwsException $error) {
            throw new StorageException('Unable to read the S3 object.', 0, $error);
        }
    }

    /** @param resource $stream
     * @return array{size: int, sha256: string} */
    private function fingerprint(mixed $stream, int $expectedSize, ?string $expectedDigest = null): array
    {
        $hash = hash_init('sha256');
        $size = hash_update_stream($hash, $stream);
        $digest = hash_final($hash);
        if ($size !== $expectedSize || ($expectedDigest !== null && !hash_equals($expectedDigest, $digest))) {
            throw new StorageException('The S3 content does not match its declared size or digest.');
        }
        return ['size' => $size, 'sha256' => $digest];
    }

    private function metadata(\Aws\ResultInterface $result): array
    {
        $size = filter_var($result['ContentLength'], FILTER_VALIDATE_INT);
        if ($size === false || $size < 0) {
            throw new StorageException('The S3 object has no valid content length.');
        }
        $original = $result['Metadata']['created-at'] ?? null;
        $digest = $result['Metadata']['sha256'] ?? null;
        if (($original !== null && (!ctype_digit($original) || strlen($original) > 18)) || ($digest !== null && !preg_match('/^[a-f0-9]{64}$/', $digest))) {
            throw new StorageException('Invalid S3 lifecycle or digest metadata.');
        }
        if ($original === null && !$result['LastModified'] instanceof \DateTimeInterface) {
            throw new StorageException('The S3 object has no lifecycle timestamp.');
        }
        return [
            'size' => $size,
            'mime' => $result['ContentType'] ?? 'application/octet-stream',
            'createdAt' => $original !== null ? (int) $original : $result['LastModified']->getTimestamp(),
            'sha256' => $digest,
        ];
    }
}
