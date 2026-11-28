<?php

declare(strict_types=1);

namespace Amea\Cache;

use Amea\Core\FileWriteGate;
use Throwable;

/**
 * Atomic, zero-daemon application cache for shared-hosting and split-container runtimes.
 *
 * Uses a two-tier lookup (in-process memory + atomic JSON files in a shared directory)
 * with namespace-scoped invalidation, write-gate awareness, and fail-open behavior so
 * cache errors never interrupt application traffic.
 */
final class AppCache
{
    public const NS_SETTINGS = 'settings';
    public const NS_SLIDER = 'slider';
    public const NS_JOURNAL = 'journal';
    public const NS_LOOKUPS = 'lookups';

    /** @var array<string, array{expires: int, value: mixed}> */
    private array $memory = [];

    private array $generations = [];
    private string $scopeHash;

    private function generation(string $name): string
    {
        if ($this->directory === null) {
            return (string) ($this->generations[$name] ?? 0);
        }
        return (string) @file_get_contents($this->directory . '/' . $this->scopeHash . '.' . $name . '.generation');
    }

    private function versionedKey(string $namespace, string $key): string
    {
        return $this->generation('scope') . ':' . $this->generation('ns-' . $this->sanitizeNamespace($namespace)) . ':' . $key;
    }

    private function mutate(callable $operation): mixed
    {
        try {
            return $this->writeGateDir ? (new FileWriteGate($this->writeGateDir))->mutate($operation) : $operation();
        } catch (Throwable) {
            return null;
        }
    }

    public function __construct(
        private ?string $directory = null,
        string $scope = 'default',
        private ?string $writeGateDir = null
    ) {
        $this->scopeHash = substr(hash('sha256', $scope), 0, 12);
    }

    public static function memoryOnly(string $scope = 'memory'): self
    {
        return new self(null, $scope, null);
    }

    public function remember(string $namespace, string $key, int $ttlSeconds, callable $loader): mixed
    {
        $version = $this->versionedKey($namespace, $key);
        [$hit, $value] = $this->fetch($namespace, $key);
        if ($hit) {
            return $value;
        }

        $value = $loader();
        if ($ttlSeconds > 0) {
            if ($version !== $this->versionedKey($namespace, $key)) {
                return $loader();
            }
            $this->store($namespace, $version, $value, $ttlSeconds);
            if ($version !== $this->versionedKey($namespace, $key)) {
                return $loader();
            }
        }

        return $value;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    public function fetch(string $namespace, string $key): array
    {
        $ns = $this->sanitizeNamespace($namespace);
        $key = $this->versionedKey($namespace, $key);
        $memKey = $ns . ':' . $key;
        $now = time();

        if (isset($this->memory[$memKey])) {
            if ($this->memory[$memKey]['expires'] > $now) {
                return [true, $this->memory[$memKey]['value']];
            }
            unset($this->memory[$memKey]);
        }

        $path = $this->entryPath($ns, $key);
        if ($path === null || !is_file($path)) {
            return [false, null];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [false, null];
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->unlinkIfWritable($path);
            return [false, null];
        }

        if (!is_array($payload) || !isset($payload['expires']) || !is_int($payload['expires']) || !array_key_exists('value', $payload)) {
            $this->unlinkIfWritable($path);
            return [false, null];
        }

        if ($payload['expires'] <= $now) {
            $this->unlinkIfWritable($path);
            return [false, null];
        }

        $this->memory[$memKey] = ['expires' => $payload['expires'], 'value' => $payload['value']];
        return [true, $payload['value']];
    }

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        [$hit, $value] = $this->fetch($namespace, $key);
        return $hit ? $value : $default;
    }

    public function set(string $namespace, string $key, mixed $value, int $ttlSeconds): void
    {
        $this->store($namespace, $this->versionedKey($namespace, $key), $value, $ttlSeconds);
    }

    private function store(string $namespace, string $key, mixed $value, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }
        $ns = $this->sanitizeNamespace($namespace);
        $expires = time() + $ttlSeconds;
        $this->memory[$ns . ':' . $key] = ['expires' => $expires, 'value' => $value];

        if ($this->directory === null) {
            return;
        }

        $path = $this->entryPath($ns, $key);
        if ($path === null) {
            return;
        }

        try {
            $encoded = json_encode(['expires' => $expires, 'value' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            return;
        }

        $this->mutate(function () use ($path, $encoded) {
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
                return;
            }

            $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, $encoded, LOCK_EX) === false) {
                @unlink($tmp);
                return;
            }
            @chmod($tmp, 0660);
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
            }
        });
    }

    public function invalidateNamespace(string $namespace): int
    {
        $ns = $this->sanitizeNamespace($namespace);
        $prefix = $ns . ':';
        foreach (array_keys($this->memory) as $memKey) {
            if (str_starts_with($memKey, $prefix)) {
                unset($this->memory[$memKey]);
            }
        }

        return $this->rotate('ns-' . $ns, $this->scopeHash . '-' . $ns . '-*.json');
    }

    public function clear(): int
    {
        $this->memory = [];
        return $this->rotate('scope', $this->scopeHash . '-*.json');
    }

    /**
     * @return array{mode: string, directory: ?string, entries: int, bytes: int}
     */
    public function stats(): array
    {
        if ($this->directory === null) {
            return [
                'mode' => 'memory',
                'directory' => null,
                'entries' => count($this->memory),
                'bytes' => 0,
            ];
        }

        $entries = 0;
        $bytes = 0;
        if (is_dir($this->directory)) {
            $pattern = rtrim($this->directory, '/') . '/' . $this->scopeHash . '-*.json';
            foreach (glob($pattern) ?: [] as $file) {
                if (is_file($file) && !is_link($file)) {
                    $entries++;
                    $size = @filesize($file);
                    if (is_int($size)) {
                        $bytes += $size;
                    }
                }
            }
        }

        return [
            'mode' => 'file',
            'directory' => $this->directory,
            'entries' => $entries,
            'bytes' => $bytes,
        ];
    }

    private function rotate(string $name, string $pattern): int
    {
        if ($this->directory === null) {
            $this->generations[$name] = ($this->generations[$name] ?? 0) + 1;
            return 0;
        }
        return (int) $this->mutate(function () use ($name, $pattern) {
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
                return 0;
            }
            $marker = $this->directory . '/' . $this->scopeHash . '.' . $name . '.generation';
            $token = bin2hex(random_bytes(16));
            $tmp = $marker . '.' . $token;
            if (@file_put_contents($tmp, $token) !== false && !@rename($tmp, $marker)) {
                @unlink($tmp);
            }
            return $this->unlinkMatching($this->directory . '/' . $pattern);
        });
    }

    private function unlinkMatching(string $pattern): int
    {
        if ($this->directory === null || !is_dir($this->directory)) {
            return 0;
        }

        $removed = 0;
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file) && !is_link($file) && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    private function unlinkIfWritable(string $path): void
    {
        $this->mutate(static fn () => @unlink($path));
    }

    private function entryPath(string $namespace, string $key): ?string
    {
        if ($this->directory === null) {
            return null;
        }
        return rtrim($this->directory, '/') . '/' . $this->scopeHash . '-' . $namespace . '-' . hash('sha256', $key) . '.json';
    }

    private function sanitizeNamespace(string $namespace): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $namespace);
        return ($clean === null || $clean === '') ? 'default' : substr($clean, 0, 40);
    }
}
