<?php

namespace Amea\Core;

use RuntimeException;

/** Shared persistent admission, always acquired before DB/member/backend locks. */
final class FileWriteGate
{
    private static array $admissions = [];
    private static ?self $lifetime = null;
    private string $directory;
    private bool $exclusive = false;

    public function __construct(string $directory)
    {
        if (!str_starts_with($directory, '/') || is_link($directory)) {
            throw new RuntimeException('The write gate requires an absolute persistent directory.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the persistent write gate.');
        }
        $canonical = realpath($directory);
        if ($canonical === false || (fileperms($canonical) & 0077) !== 0) {
            throw new RuntimeException('The write gate directory must be private.');
        }
        $this->directory = $canonical;
    }

    public static function application(): self
    {
        $directory = $_ENV['FILE_WRITE_GATE_PATH'] ?? getenv('FILE_WRITE_GATE_PATH');
        return new self($directory ?: dirname(__DIR__, 2) . '/storage/write-gate');
    }

    public function mutate(callable $operation): mixed
    {
        $this->enter();
        try {
            return $operation();
        } finally {
            $this->leave();
        }
    }

    /** exit() skips finally. Shutdown explicitly persists sessions before unlock. */
    public static function admitProcess(): void
    {
        if (self::$lifetime !== null) {
            return;
        }
        $gate = self::application();
        $gate->enter();
        self::$lifetime = $gate;
        register_shutdown_function(static function () use ($gate) {
            try {
                if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
                    error_log('[write-gate] Session persistence failed before releasing admission.');
                }
            } finally {
                $gate->leave();
                self::$lifetime = null;
            }
        });
    }

    public static function admitHttp(): void
    {
        if (Session::isReadOnly()) {
            return;
        }
        try {
            self::admitProcess();
        } catch (\Throwable) {
            try {
                $state = self::application()->status();
                if ($state['paused'] && $state['drained'] && Session::allowPausedRead()) {
                    return;
                }
            } catch (\Throwable) {
                // An unreadable gate or session cannot grant a maintenance read.
            }
            http_response_code(503);
            header('Retry-After: 60');
            header('Cache-Control: no-store');
            exit('Maintenance en cours. Veuillez réessayer plus tard.');
        }
    }

    public function pause(array $selection = []): array
    {
        return $this->operator(function () use ($selection) {
            $state = $this->status();
            if (!$state['paused']) {
                $this->writeState(['version' => 1, 'paused' => true, 'drained' => false,
                    'paused_at' => microtime(true), 'challenge' => bin2hex(random_bytes(24)), 'selection' => $selection]);
            } elseif (($state['selection'] ?? []) !== $selection) {
                throw new RuntimeException('The paused gate belongs to a different migration selection.');
            }
            return $this->drain(static fn (array $state) => $state);
        });
    }

    /** Privileged migration work bypasses admission while retaining exclusive drain. */
    public function exclusive(callable $operation): mixed
    {
        return $this->operator(fn () => $this->drain($operation));
    }

    public function resume(callable $verify): void
    {
        $this->exclusive(function (array $state) use ($verify) {
            $verify($state);
            if (!unlink($this->directory . '/paused.json')) {
                throw new RuntimeException('Unable to reopen the write gate.');
            }
            $this->syncDirectory();
        });
    }

    public function record(array $state): void
    {
        if (!$this->exclusive || empty($state['paused']) || empty($state['drained'])) {
            throw new RuntimeException('Migration transitions require completed admission drain.');
        }
        $this->writeState($state);
    }

    public function status(): array
    {
        $path = $this->directory . '/paused.json';
        clearstatcache(true, $path);
        if (!file_exists($path) && !is_link($path)) {
            return ['paused' => false, 'drained' => false];
        }
        $this->assertFile($path);
        $state = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['paused'] ?? null) !== true || !is_bool($state['drained'] ?? null)) {
            throw new RuntimeException('Invalid durable pause state. Admission remains closed.');
        }
        return $state;
    }

    public function identity(): string
    {
        $handle = $this->openLock('admission.lock');
        try {
            $stat = fstat($handle);
            return hash('sha256', json_encode([$stat['dev'], $stat['ino']], JSON_THROW_ON_ERROR));
        } finally {
            fclose($handle);
        }
    }

    private function enter(): void
    {
        if (isset(self::$admissions[$this->directory])) {
            self::$admissions[$this->directory]['depth']++;
            return;
        }
        if ($this->status()['paused']) {
            throw new RuntimeException('File writes are paused.');
        }
        $handle = $this->openLock('admission.lock');
        try {
            if (!flock($handle, LOCK_SH | LOCK_NB) || $this->status()['paused']) {
                throw new RuntimeException('File writes are paused or draining.');
            }
            self::$admissions[$this->directory] = ['handle' => $handle, 'depth' => 1];
        } catch (\Throwable $error) {
            fclose($handle);
            throw $error;
        }
    }

    private function leave(): void
    {
        if (--self::$admissions[$this->directory]['depth'] === 0) {
            fclose(self::$admissions[$this->directory]['handle']);
            unset(self::$admissions[$this->directory]);
        }
    }

    private function operator(callable $operation): mixed
    {
        if (isset(self::$admissions[$this->directory])) {
            throw new RuntimeException('An admitted writer cannot become a migration operator.');
        }
        $handle = $this->openLock('operator.lock');
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another write-gate operator is active.');
            }
            return $operation();
        } finally {
            fclose($handle);
        }
    }

    private function drain(callable $operation): mixed
    {
        if (!$this->status()['paused']) {
            throw new RuntimeException('Explicit pause is required before final migration work.');
        }
        $handle = $this->openLock('admission.lock');
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to drain active writers.');
            }
            $state = $this->status();
            $state['drained'] = true;
            $state['drained_at'] = microtime(true);
            $this->writeState($state);
            $this->exclusive = true;
            return $operation($state);
        } finally {
            $this->exclusive = false;
            fclose($handle);
        }
    }

    private function openLock(string $name): mixed
    {
        $path = $this->directory . '/' . $name;
        $handle = @fopen($path, 'x+b');
        if ($handle !== false) {
            chmod($path, 0600);
        } else {
            $this->assertFile($path);
            $handle = @fopen($path, 'r+b');
        }
        if ($handle === false) {
            throw new RuntimeException('Unable to open the persistent gate lock.');
        }
        return $handle;
    }

    private function assertFile(string $path): void
    {
        if (is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('Write-gate state must be private regular files.');
        }
    }

    private function writeState(array $state): void
    {
        $temporary = tempnam($this->directory, '.pause-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to allocate durable pause state.');
        }
        $handle = null;
        try {
            chmod($temporary, 0600);
            $handle = fopen($temporary, 'wb');
            $bytes = json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if ($handle === false || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || !fsync($handle)) {
                throw new RuntimeException('Unable to persist pause state.');
            }
            fclose($handle);
            $handle = null;
            if (!rename($temporary, $this->directory . '/paused.json')) {
                throw new RuntimeException('Unable to publish pause state.');
            }
            $this->syncDirectory();
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function syncDirectory(): void
    {
        $handle = fopen($this->directory, 'r');
        try {
            if ($handle === false || !fsync($handle)) {
                throw new RuntimeException('Unable to synchronize the write-gate directory.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
