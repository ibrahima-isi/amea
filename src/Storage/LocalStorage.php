<?php

namespace Amea\Storage;

class LocalStorage implements Storage
{
    use ValidatesStorageKeys;

    private string $root;

    public function __construct(string $projectRoot)
    {
        $root = realpath($projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new StorageException('The local storage project root does not exist.');
        }
        $this->root = $root;
    }

    public function read(string $key): mixed
    {
        return $this->locked(function () use ($key) {
            $path = $this->path($key);
            $stream = @fopen($path, 'rb');
            if ($stream === false) {
                throw new StorageException('Unable to read the storage object.');
            }
            return $stream;
        }, LOCK_SH);
    }

    public function write(string $key, mixed $source, string $mime, int $createdAt): array
    {
        $this->path($key);
        if (!is_resource($source) || get_resource_type($source) !== 'stream' || !strpbrk(stream_get_meta_data($source)['mode'], 'r+')) {
            throw new StorageException('Storage writes require a readable stream.');
        }
        if ($mime === '' || strlen($mime) > 255 || preg_match('/[\x00-\x1f\x7f]/', $mime) || $createdAt < 0) {
            throw new StorageException('Invalid storage content metadata.');
        }
        return $this->locked(function () use ($key, $source, $mime, $createdAt) {
            $path = $this->path($key);
            $this->ensureDirectory(dirname($path));
            // Keep hard-link publication on the uploads filesystem. The exclusive
            // lock excludes live spools from concurrent backend reads and listings.
            $temporary = @tempnam(dirname($path), '.storage-');
            if ($temporary === false || dirname($temporary) !== dirname($path)) {
                if ($temporary !== false) {
                    unlink($temporary);
                }
                throw new StorageException('Unable to allocate the storage object.');
            }
            $output = @fopen($temporary, 'wb');
            $published = false;
            try {
                if ($output === false) {
                    throw new StorageException('Unable to open the upload spool.');
                }
                $size = @stream_copy_to_stream($source, $output);
                if ($size === false || !fflush($output)) {
                    throw new StorageException('Unable to write the storage object.');
                }
                fclose($output);
                $output = null;
                $digest = hash_file('sha256', $temporary);
                if ($digest === false || !@touch($temporary, $createdAt) || !@chmod($temporary, 0660)) {
                    throw new StorageException('Unable to prepare the storage object.');
                }
                if (!@link($temporary, $path)) {
                    $this->path($key);
                    if (is_file($path) && filesize($path) === $size && hash_file('sha256', $path) === $digest) {
                        $metadata = $this->inspect($key);
                        if ($metadata['sha256'] === null) {
                            $this->saveMetadata($key, ['mime' => $mime, 'createdAt' => $metadata['createdAt'], 'sha256' => $digest]);
                        }
                        return ['size' => $size, 'sha256' => $digest];
                    }
                    throw new StorageException('The storage destination contains conflicting content or cannot be created.');
                }
                $published = true;
                $this->saveMetadata($key, ['mime' => $mime, 'createdAt' => $createdAt, 'sha256' => $digest]);
                $published = false;
                return ['size' => $size, 'sha256' => $digest];
            } finally {
                if (is_resource($output)) {
                    fclose($output);
                }
                if ($published) {
                    unlink($path);
                }
                unlink($temporary);
            }
        });
    }

    public function stat(string $key): ?array
    {
        return $this->locked(fn () => $this->inspect($key), LOCK_SH);
    }

    public function delete(string $key): void
    {
        $this->locked(function () use ($key) {
            $path = $this->path($key);
            if (is_file($path) && !@unlink($path)) {
                throw new StorageException('Unable to delete the storage object.');
            }
            $metadata = $this->metadataPath($key);
            if (is_file($metadata) && !@unlink($metadata)) {
                throw new StorageException('Unable to delete storage metadata.');
            }
        });
    }

    public function copy(string $sourceKey, string $destinationKey): void
    {
        $this->path($destinationKey);
        [$source, $metadata] = $this->locked(function () use ($sourceKey) {
            $metadata = $this->inspect($sourceKey);
            if ($metadata === null) {
                throw new StorageException('The storage source does not exist.');
            }
            $source = @fopen($this->path($sourceKey), 'rb');
            if ($source === false) {
                throw new StorageException('Unable to read the storage source.');
            }
            return [$source, $metadata];
        }, LOCK_SH);
        try {
            $this->write($destinationKey, $source, $metadata['mime'], $metadata['createdAt']);
        } finally {
            fclose($source);
        }
    }

    public function listPage(string $prefix, ?string $cursor, int $limit): array
    {
        $prefix = $prefix === '' ? 'uploads/' : $prefix;
        $this->validateKey(str_ends_with($prefix, '/') ? $prefix . 'prefix-check' : $prefix);
        $this->checkedPath(rtrim($prefix, '/'));
        if ($limit < 1 || $limit > 1000) {
            throw new StorageException('The storage page limit must be between 1 and 1000.');
        }
        $after = '';
        if ($cursor !== null) {
            $decoded = base64_decode($cursor, true);
            $page = $decoded === false ? null : json_decode($decoded, true);
            if (!is_array($page) || ($page['prefix'] ?? null) !== $prefix || !is_string($page['after'] ?? null) || !str_starts_with($page['after'], $prefix)) {
                throw new StorageException('Invalid storage cursor.');
            }
            $after = $page['after'];
            $this->path($after);
        }
        return $this->locked(function () use ($prefix, $after, $limit) {
            $objects = [];
            $uploads = $this->checkedPath('uploads');
            if (!is_dir($uploads)) {
                return ['objects' => [], 'cursor' => null];
            }
            try {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    $key = substr($file->getPathname(), strlen($this->root) + 1);
                    if ($file->isLink() || !$file->isFile()) {
                        throw new StorageException('Storage listings contain a nonregular object.');
                    }
                    if (!str_starts_with($key, $prefix) || strcmp($key, $after) <= 0) {
                        continue;
                    }
                    $metadata = $this->inspect($key);
                    $objects[$key] = ['key' => $key, 'size' => $metadata['size'], 'createdAt' => $metadata['createdAt']];
                    ksort($objects, SORT_STRING);
                    if (count($objects) > $limit + 1) {
                        array_pop($objects);
                    }
                }
            } catch (\UnexpectedValueException $error) {
                throw new StorageException('Unable to list local storage.', 0, $error);
            }
            $hasMore = count($objects) > $limit;
            if ($hasMore) {
                array_pop($objects);
            }
            return ['objects' => array_values($objects), 'cursor' => $hasMore ? base64_encode(json_encode(['prefix' => $prefix, 'after' => array_key_last($objects)], JSON_THROW_ON_ERROR)) : null];
        }, LOCK_SH);
    }

    private function inspect(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }
        $metadataPath = $this->metadataPath($key);
        $metadata = [];
        if (is_file($metadataPath)) {
            $json = @file_get_contents($metadataPath);
            if ($json === false) {
                throw new StorageException('Unable to read storage metadata.');
            }
            try {
                $metadata = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $error) {
                throw new StorageException('Invalid local storage metadata.', 0, $error);
            }
            if (!is_array($metadata) || !is_string($metadata['mime'] ?? null) || !is_int($metadata['createdAt'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $metadata['sha256'] ?? '')) {
                throw new StorageException('Invalid local storage metadata.');
            }
        }
        $size = @filesize($path);
        $createdAt = $metadata['createdAt'] ?? @filemtime($path);
        $mime = $metadata['mime'] ?? (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($size === false || $createdAt === false || $mime === false || !is_readable($path)) {
            throw new StorageException('Unable to inspect the local storage object.');
        }
        return ['size' => $size, 'mime' => $mime, 'createdAt' => $createdAt, 'sha256' => $metadata['sha256'] ?? null];
    }

    private function path(string $key): string
    {
        $this->validateKey($key);
        $path = $this->checkedPath($key);
        if (file_exists($path) && !is_file($path)) {
            throw new StorageException('The storage object is not a regular file.');
        }
        return $path;
    }

    private function checkedPath(string $relative): string
    {
        $path = $this->root;
        foreach (explode('/', $relative) as $segment) {
            if (is_dir($path) && (!is_readable($path) || !is_executable($path))) {
                throw new StorageException('A storage directory is inaccessible.');
            }
            if (file_exists($path) && !is_dir($path)) {
                throw new StorageException('A storage parent is not a directory.');
            }
            $path .= '/' . $segment;
            clearstatcache(true, $path);
            if (is_link($path)) {
                throw new StorageException('Storage symlinks are forbidden.');
            }
        }
        return $path;
    }

    private function ensureDirectory(string $path): void
    {
        $relative = substr($path, strlen($this->root) + 1);
        $this->checkedPath($relative);
        if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) {
            throw new StorageException('Unable to create a storage directory.');
        }
        $this->checkedPath($relative . '/directory-check');
    }

    private function metadataDirectory(): string
    {
        return $this->checkedPath('storage/object-storage');
    }

    private function metadataPath(string $key): string
    {
        return $this->checkedPath('storage/object-storage/' . hash('sha256', $key) . '.json');
    }

    private function saveMetadata(string $key, array $metadata): void
    {
        $path = $this->metadataPath($key);
        $temporary = @tempnam($this->metadataDirectory(), 'metadata-');
        if ($temporary === false || dirname($temporary) !== $this->metadataDirectory()) {
            if ($temporary !== false) {
                unlink($temporary);
            }
            throw new StorageException('Unable to allocate object metadata.');
        }
        try {
            if (@file_put_contents($temporary, json_encode($metadata, JSON_THROW_ON_ERROR)) === false || !@rename($temporary, $path)) {
                throw new StorageException('Unable to store object metadata.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function locked(callable $operation, int $mode = LOCK_EX): mixed
    {
        $this->ensureDirectory($this->metadataDirectory());
        $path = $this->checkedPath('storage/object-storage/.lock');
        $lock = @fopen($path, 'c+b');
        if ($lock === false) {
            throw new StorageException('Unable to open the local storage lock.');
        }
        try {
            if (!flock($lock, $mode)) {
                throw new StorageException('Unable to acquire the local storage lock.');
            }
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
