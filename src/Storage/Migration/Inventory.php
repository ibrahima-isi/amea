<?php

namespace Amea\Storage\Migration;

use Amea\Storage\LocalStorage;
use Amea\Storage\Storage;
use Amea\Storage\StorageException;
use Amea\Storage\ValidatesStorageKeys;

final class Inventory
{
    use ValidatesStorageKeys;

    public function __construct(private Storage $source, private array $identity, private ?string $localRoot = null, private int $pageSize = 500)
    {
        if (Manifest::backendIdentity($source) !== ($identity['source'] ?? null) || $pageSize < 1 || $pageSize > 1000) {
            throw new StorageException('Inventory source identity or page size is invalid.');
        }
        if ($source instanceof LocalStorage && $localRoot !== $source->migrationRoot()) {
            throw new StorageException('Local inventory requires the selected canonical source root.');
        }
    }

    public function scan(): Manifest
    {
        $manifest = new Manifest($this->identity, bin2hex(random_bytes(16)));
        if ($this->source instanceof LocalStorage) {
            $this->scanDirectory($this->localRoot . '/uploads', 'uploads', $manifest);
        } else {
            $cursor = null;
            $seen = [];
            do {
                $page = $this->source->listPage('uploads/', $cursor, $this->pageSize);
                foreach ($page['objects'] as $object) {
                    $key = $object['key'];
                    $this->validateKey($key);
                    if (isset($manifest->files[$key])) {
                        throw new StorageException('Duplicate object in paginated inventory.');
                    }
                    $metadata = $this->source->stat($key);
                    if ($metadata === null) {
                        throw new StorageException('Source disappeared during inventory.');
                    }
                    $stream = $this->source->read($key);
                    try {
                        $fingerprint = self::fingerprint($stream);
                    } finally {
                        fclose($stream);
                    }
                    if ($fingerprint['size'] !== $metadata['size']) {
                        throw new StorageException('Source changed during inventory.');
                    }
                    $manifest->files[$key] = $this->entry($key, $fingerprint, $metadata['createdAt'], $metadata['createdAt'], $metadata['mime']);
                }
                $cursor = $page['cursor'];
                if ($cursor !== null && (isset($seen[$cursor]) || $page['objects'] === [])) {
                    throw new StorageException('Paginated inventory did not advance.');
                }
                $seen[$cursor ?? ''] = true;
            } while ($cursor !== null);
        }
        ksort($manifest->files, SORT_STRING);
        return $manifest;
    }

    /** Hash actual bytes with bounded memory and fail on incomplete stream reads. */
    public static function fingerprint(mixed $stream): array
    {
        $hash = hash_init('sha256');
        $size = 0;
        while (!feof($stream)) {
            $bytes = fread($stream, 1024 * 1024);
            if ($bytes === false || ($bytes === '' && !feof($stream))) {
                throw new StorageException('Unable to read complete object bytes.');
            }
            $size += strlen($bytes);
            hash_update($hash, $bytes);
        }
        return ['size' => $size, 'sha256' => hash_final($hash)];
    }

    private function scanDirectory(string $path, string $key, Manifest $manifest): void
    {
        $stat = @lstat($path);
        if ($stat === false) {
            throw new StorageException('Source directory is missing or inaccessible.');
        }
        if (is_link($path)) {
            $manifest->issues[] = ['type' => 'symlink', 'key' => mb_check_encoding($key, 'UTF-8') ? $key : base64_encode($key), 'key_encoding' => mb_check_encoding($key, 'UTF-8') ? 'utf8' : 'base64', 'disposition' => null];
            return;
        }
        if (!is_dir($path) || !is_readable($path) || !is_executable($path)) {
            throw new StorageException('Source directory is inaccessible.');
        }
        $known = ['uploads', 'uploads/students', 'uploads/students/cvs', 'uploads/students/identity',
            'uploads/students/pending', 'uploads/students/pending/cvs', 'uploads/slider', 'uploads/journal'];
        if (!in_array($key, $known, true)) {
            $manifest->issues[] = ['type' => 'unknown-directory', 'key' => mb_check_encoding($key, 'UTF-8') ? $key : base64_encode($key), 'key_encoding' => mb_check_encoding($key, 'UTF-8') ? 'utf8' : 'base64', 'disposition' => null];
        }
        $names = @scandir($path);
        if ($names === false) {
            throw new StorageException('Unable to enumerate the complete source directory.');
        }
        foreach (array_diff($names, ['.', '..']) as $name) {
            $child = $path . '/' . $name;
            $childKey = $key . '/' . $name;
            $encoding = mb_check_encoding($childKey, 'UTF-8') ? 'utf8' : 'base64';
            $evidenceKey = $encoding === 'utf8' ? $childKey : base64_encode($childKey);
            $stat = @lstat($child);
            if ($stat === false) {
                throw new StorageException('Source entry became inaccessible.');
            }
            if (is_link($child)) {
                $manifest->issues[] = ['type' => 'symlink', 'key' => $evidenceKey, 'key_encoding' => $encoding, 'disposition' => null];
                continue;
            }
            if (is_dir($child)) {
                $this->scanDirectory($child, $childKey, $manifest);
                continue;
            }
            if (!is_file($child)) {
                $manifest->issues[] = ['type' => 'nonregular', 'key' => $evidenceKey, 'key_encoding' => $encoding, 'disposition' => null];
                continue;
            }
            $valid = true;
            try {
                $this->validateKey($childKey);
            } catch (StorageException) {
                $valid = false;
                $manifest->issues[] = ['type' => 'invalid-key', 'key' => $evidenceKey, 'key_encoding' => $encoding, 'disposition' => null];
            }
            $stream = @fopen($child, 'rb');
            if ($stream === false) {
                throw new StorageException('Unable to read a source file.');
            }
            try {
                $before = fstat($stream);
                $fingerprint = self::fingerprint($stream);
                $after = fstat($stream);
            } finally {
                fclose($stream);
            }
            if ($before === false || $after === false || $before['ino'] !== $stat['ino'] || $before['size'] !== $fingerprint['size']
                || $before['size'] !== $after['size'] || $before['mtime'] !== $after['mtime']) {
                throw new StorageException('Source changed during inventory.');
            }
            $mime = @(new \finfo(FILEINFO_MIME_TYPE))->file($child);
            if ($mime === false) {
                throw new StorageException('Unable to inspect source MIME.');
            }
            $entry = $this->entry($evidenceKey, $fingerprint, $stat['mtime'], $stat['mtime'], $mime);
            $entry['source_key_encoding'] = $encoding;
            $entry['valid_key'] = $valid;
            // Existing metadata is read directly so inventory creates no locks or files.
            $metadataPath = $this->localRoot . '/storage/object-storage/' . hash('sha256', $childKey) . '.json';
            if (is_link($this->localRoot . '/storage') || is_link($this->localRoot . '/storage/object-storage') || is_link($metadataPath)) {
                throw new StorageException('Source metadata contains a symlink.');
            }
            if (is_file($metadataPath)) {
                $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
                if (!is_int($metadata['createdAt'] ?? null) || $metadata['createdAt'] < 0 || !is_string($metadata['mime'] ?? null)) {
                    throw new StorageException('Source lifecycle metadata is invalid.');
                }
                $entry['created_at'] = $metadata['createdAt'];
                $entry['mime'] = $metadata['mime'];
            }
            $manifest->files[$evidenceKey] = $entry;
        }
    }

    private function entry(string $key, array $fingerprint, int $createdAt, int $mtime, string $mime): array
    {
        return ['source_key' => $key, 'destination_key' => $key, ...$fingerprint, 'created_at' => $createdAt,
            'source_mtime' => $mtime, 'mime' => $mime, 'copy_state' => 'pending', 'verify_state' => 'pending', 'valid_key' => true, 'source_key_encoding' => 'utf8'];
    }
}
