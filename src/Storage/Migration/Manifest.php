<?php

namespace Amea\Storage\Migration;

use Amea\Storage\Storage;
use Amea\Storage\LocalStorage;
use Amea\Storage\S3Storage;
use Amea\Storage\StorageException;
use Amea\Storage\ValidatesStorageKeys;

/** Immutable reviewed JSONL inventory plus an atomic, identity-bound checkpoint. */
final class Manifest
{
    use ValidatesStorageKeys;

    public array $files = [];
    public array $references = [];
    public array $issues = [];
    public array $history = [];
    public array $corrections = [];
    private ?string $path = null;
    private ?string $checkpointPath = null;
    private ?string $digest = null;
    private bool $locked = false;
    public readonly string $id;

    public function __construct(public readonly array $identity, ?string $id = null)
    {
        $this->id = $id ?? bin2hex(random_bytes(16));
        foreach (['environment', 'database', 'source', 'destination', 'sessions', 'serializer'] as $field) {
            if (!is_string($identity[$field] ?? null) || $identity[$field] === '') {
                throw new StorageException('Every migration target must be explicitly identified.');
            }
        }
        if ($identity['source'] === $identity['destination']) {
            throw new StorageException('Migration source and destination must be different.');
        }
    }

    public static function backendIdentity(Storage $storage): string
    {
        if (!method_exists($storage, 'migrationIdentity')) {
            throw new StorageException('The selected backend has no verifiable migration identity.');
        }
        return $storage->migrationIdentity();
    }

    public static function assertBackendPair(Storage $source, Storage $destination): void
    {
        if (self::backendIdentity($source) === self::backendIdentity($destination)) {
            throw new StorageException('Migration source and destination must be different.');
        }
        if ($source instanceof S3Storage && $destination instanceof S3Storage
            && $source->migrationNamespace() === $destination->migrationNamespace()) {
            throw new StorageException('Migration source and destination select the same S3 namespace.');
        }
        if ($source instanceof LocalStorage && $destination instanceof LocalStorage) {
            $sourceTree = $source->migrationRoot() . '/uploads/';
            $destinationTree = $destination->migrationRoot() . '/uploads/';
            if (str_starts_with($sourceTree, $destinationTree) || str_starts_with($destinationTree, $sourceTree)) {
                throw new StorageException('Migration source and destination upload trees overlap.');
            }
        }
    }

    public function assertIdentity(array $identity): void
    {
        if ($this->identity !== $identity) {
            throw new StorageException('Migration target identity does not match the reviewed manifest.');
        }
    }

    public function reviewIdentity(): string
    {
        if ($this->digest === null) {
            throw new StorageException('Save the inventory before reviewing its identity.');
        }
        return $this->digest;
    }

    public function save(string $path): void
    {
        self::assertPrivatePath($path);
        $this->path = $path;
        $this->checkpointPath = null;
        $this->withFileLock(function () use ($path) {
            if (file_exists($path)) {
                throw new StorageException('An inventory cannot replace an existing manifest.');
            }
            $content = $this->encode();
            self::atomicWrite($path, $content);
            $this->digest = hash('sha256', $content);
        });
    }

    public static function load(string $path, array $identity, ?string $checkpointPath = null): self
    {
        self::assertPrivatePath($path);
        $content = self::privateRead($path);
        $manifest = self::decode($content);
        $manifest->assertIdentity($identity);
        $manifest->path = $path;
        $manifest->checkpointPath = $checkpointPath;
        $manifest->digest = hash('sha256', $content);
        $checkpoint = $checkpointPath ?? $path . '.checkpoint';
        if (file_exists($checkpoint) || is_link($checkpoint)) {
            $saved = json_decode(self::privateRead($checkpoint), true, 512, JSON_THROW_ON_ERROR);
            if (($saved['manifest_sha256'] ?? null) !== $manifest->digest || !is_string($saved['state'] ?? null)
                || !hash_equals((string) ($saved['state_sha256'] ?? ''), hash('sha256', $saved['state']))) {
                throw new StorageException('Checkpoint identity or integrity mismatch.');
            }
            $current = self::decode($saved['state']);
            $current->assertIdentity($identity);
            if ($current->id !== $manifest->id) {
                throw new StorageException('Checkpoint belongs to another manifest.');
            }
            $manifest->adopt($current);
        }
        return $manifest;
    }

    /** Reload under one writer lock before inspecting or advancing durable state. */
    public function locked(callable $operation): mixed
    {
        if ($this->path === null) {
            throw new StorageException('Migration requires a saved, reviewed inventory.');
        }
        return $this->withFileLock(function () use ($operation) {
            $current = self::load($this->path, $this->identity, $this->checkpointPath);
            if ($current->reviewIdentity() !== $this->reviewIdentity()) {
                throw new StorageException('Reviewed inventory changed.');
            }
            $this->adopt($current);
            return $operation($this);
        });
    }

    public function checkpoint(): void
    {
        if (!$this->locked || $this->path === null || $this->digest === null) {
            throw new StorageException('Checkpoint updates require the manifest writer lock.');
        }
        $state = $this->encode();
        self::atomicWrite($this->checkpointPath ?? $this->path . '.checkpoint', json_encode([
            'manifest_sha256' => $this->digest, 'state_sha256' => hash('sha256', $state), 'state' => $state,
        ], JSON_THROW_ON_ERROR));
    }

    public function map(string $source, string $destination, string $expectedSha256): void
    {
        $this->validateKey($destination);
        if (!isset($this->files[$source]) || $this->files[$source]['sha256'] !== $expectedSha256) {
            throw new StorageException('Reviewed mapping does not match source bytes.');
        }
        foreach ($this->files as $key => $file) {
            if ($key !== $source && $file['destination_key'] === $destination) {
                throw new StorageException('Conflicting destination mappings.');
            }
        }
        if ($this->files[$source]['destination_key'] !== $destination) {
            $this->files[$source]['destination_key'] = $destination;
            $this->files[$source]['copy_state'] = 'pending';
            $this->files[$source]['verify_state'] = 'pending';
        }
    }

    public function dispose(array $dispositions, string $reviewedDigest): void
    {
        if (!$this->locked || $reviewedDigest !== hash('sha256', json_encode($dispositions, JSON_THROW_ON_ERROR))) {
            throw new StorageException('Exception dispositions require the writer lock and reviewed identity.');
        }
        foreach ($dispositions as $disposition) {
            $decision = $disposition['decision'] ?? null;
            if (!is_string($decision) || trim($decision) === '' || strlen($decision) > 2000) {
                throw new StorageException('A reviewed disposition requires its explicit decision.');
            }
            $matched = false;
            if (($disposition['type'] ?? null) === 'file') {
                $key = $disposition['source_key'] ?? '';
                $file = $this->files[$key] ?? null;
                if ($file !== null && $file['sha256'] === ($disposition['expected_sha256'] ?? null)
                    && (($file['source_state'] ?? 'present') === 'removed' || !$file['valid_key'])) {
                    $this->files[$key]['disposition'] = $decision;
                    $matched = true;
                }
            } elseif (($disposition['type'] ?? null) === 'issue') {
                foreach ($this->issues as &$issue) {
                    if ($issue['key'] === ($disposition['key'] ?? null) && $issue['type'] === ($disposition['issue_type'] ?? null)) {
                        $issue['disposition'] = $decision;
                        $matched = true;
                    }
                }
                unset($issue);
            } elseif (($disposition['type'] ?? null) === 'reference') {
                foreach ($this->references as &$reference) {
                    if ($reference['owner_type'] === ($disposition['owner_type'] ?? null)
                        && $reference['table'] === ($disposition['table'] ?? null) && $reference['record'] === ($disposition['record'] ?? null)
                        && $reference['column'] === ($disposition['column'] ?? null) && $reference['index'] === ($disposition['index'] ?? null)
                        && $reference['original_value'] === ($disposition['expected_old'] ?? null) && $reference['state'] === 'missing') {
                        $reference['disposition'] = $decision;
                        $matched = true;
                    }
                }
                unset($reference);
            }
            if (!$matched) {
                throw new StorageException('Reviewed disposition no longer matches its inventoried exception.');
            }
        }
        $this->history[] = ['type' => 'reviewed-dispositions', 'sha256' => $reviewedDigest, 'dispositions' => $dispositions, 'at' => time()];
    }

    public static function assertPrivatePath(string $path): void
    {
        if (!str_starts_with($path, '/') || str_contains($path, "\0") || basename($path) === '.' || basename($path) === '..') {
            throw new StorageException('Evidence requires an absolute private path.');
        }
        $directory = realpath(dirname($path));
        $webRoot = dirname(__DIR__, 3);
        if ($directory === false || $directory !== dirname($path) || !is_dir($directory)
            || ($directory === $webRoot || str_starts_with($directory, $webRoot . '/'))
            || (fileperms($directory) & 0077) !== 0) {
            throw new StorageException('Evidence directory must be private, canonical and outside the web root.');
        }
        if (is_link($path) || (file_exists($path) && (!is_file($path) || (fileperms($path) & 0077) !== 0))) {
            throw new StorageException('Evidence must be a private regular file.');
        }
    }

    private static function privateRead(string $path): string
    {
        self::assertPrivatePath($path);
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new StorageException('Unable to read private migration evidence.');
        }
        return $bytes;
    }

    private function encode(): string
    {
        $destinations = [];
        foreach ($this->files as $file) {
            if (isset($destinations[$file['destination_key']])) {
                throw new StorageException('Conflicting destination mappings.');
            }
            $destinations[$file['destination_key']] = true;
        }
        $rows = [['type' => 'header', 'version' => 1, 'id' => $this->id, 'identity' => $this->identity]];
        foreach (['files' => 'file', 'references' => 'reference', 'issues' => 'issue', 'history' => 'history', 'corrections' => 'correction'] as $property => $type) {
            foreach ($this->$property as $row) {
                $rows[] = ['type' => $type, 'value' => $row];
            }
        }
        return implode("\n", array_map(static fn ($row) => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $rows)) . "\n";
    }

    private static function decode(string $content): self
    {
        $lines = explode("\n", rtrim($content, "\n"));
        $header = json_decode(array_shift($lines), true, 512, JSON_THROW_ON_ERROR);
        if (($header['type'] ?? null) !== 'header' || ($header['version'] ?? null) !== 1 || !is_string($header['id'] ?? null)
            || !preg_match('/^[a-f0-9]{32}$/', $header['id'])) {
            throw new StorageException('Unsupported migration manifest.');
        }
        $manifest = new self($header['identity'] ?? [], $header['id']);
        $destinations = [];
        foreach ($lines as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $value = $row['value'] ?? null;
            if (!is_array($value)) {
                throw new StorageException('Invalid migration entry.');
            }
            if (($row['type'] ?? null) === 'file') {
                $key = $value['source_key'] ?? null;
                foreach (['source_key', 'destination_key', 'sha256', 'copy_state', 'verify_state', 'mime'] as $field) {
                    if (!is_string($value[$field] ?? null)) {
                        throw new StorageException('Invalid file inventory fields.');
                    }
                }
                foreach (['size', 'created_at', 'source_mtime'] as $field) {
                    if (!is_int($value[$field] ?? null) || $value[$field] < 0) {
                        throw new StorageException('Invalid file inventory metadata.');
                    }
                }
                if (!preg_match('/^[a-f0-9]{64}$/', $value['sha256']) || isset($manifest->files[$key]) || isset($destinations[$value['destination_key']])) {
                    throw new StorageException('Invalid digest or conflicting duplicate mapping.');
                }
                if (!is_bool($value['valid_key'] ?? null)) {
                    throw new StorageException('Invalid file key validity.');
                }
                if ($value['valid_key']) {
                    $manifest->validateKey($key);
                    $manifest->validateKey($value['destination_key']);
                }
                $manifest->files[$key] = $value;
                $destinations[$value['destination_key']] = true;
                continue;
            }
            $property = match ($row['type'] ?? '') {
                'reference' => 'references', 'issue' => 'issues', 'history' => 'history', 'correction' => 'corrections',
                default => throw new StorageException('Unknown manifest entry type.'),
            };
            $manifest->{$property}[] = $value;
        }
        return $manifest;
    }

    private function adopt(self $current): void
    {
        foreach (['files', 'references', 'issues', 'history', 'corrections'] as $property) {
            $this->$property = $current->$property;
        }
    }

    private function withFileLock(callable $operation): mixed
    {
        $path = $this->path . '.lock';
        self::assertPrivatePath($path);
        $handle = @fopen($path, 'x+b');
        if ($handle !== false) {
            chmod($path, 0600);
        } else {
            $handle = @fopen($path, 'r+b');
        }
        if ($handle === false) {
            throw new StorageException('Unable to open the migration writer lock.');
        }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new StorageException('Another migration writer owns this manifest.');
            }
            $this->locked = true;
            return $operation();
        } finally {
            $this->locked = false;
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function atomicWrite(string $path, string $content): void
    {
        self::assertPrivatePath($path);
        $temporary = tempnam(dirname($path), '.migration-');
        if ($temporary === false) {
            throw new StorageException('Unable to allocate migration evidence.');
        }
        $handle = null;
        try {
            if (!chmod($temporary, 0600) || ($handle = fopen($temporary, 'wb')) === false) {
                throw new StorageException('Unable to protect migration evidence.');
            }
            $offset = 0;
            while ($offset < strlen($content)) {
                $written = fwrite($handle, substr($content, $offset));
                if ($written === false || $written === 0) {
                    throw new StorageException('Unable to write migration evidence.');
                }
                $offset += $written;
            }
            if (!fflush($handle) || !fsync($handle)) {
                throw new StorageException('Unable to flush migration evidence.');
            }
            fclose($handle);
            $handle = null;
            if (!rename($temporary, $path)) {
                throw new StorageException('Unable to publish migration evidence atomically.');
            }
            $directory = @fopen(dirname($path), 'r');
            if ($directory === false) {
                throw new StorageException('Unable to open the evidence directory for durable publication.');
            }
            try {
                if (!fsync($directory)) {
                    throw new StorageException('Unable to persist the evidence directory.');
                }
            } finally {
                fclose($directory);
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
