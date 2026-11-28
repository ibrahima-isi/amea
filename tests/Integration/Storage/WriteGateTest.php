<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Cache\AppCache;
use Amea\Core\FileWriteGate;
use PHPUnit\Framework\TestCase;

class WriteGateTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/amea-gate-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testPauseClosesAdmissionBeforeDrainAndNestedWriterFinishes(): void
    {
        $writer = $this->start('writer');
        $this->waitFor($this->directory . '/admitted');
        $pause = $this->start('pause');
        $this->waitFor($this->directory . '/gate/paused.json');
        $gate = new FileWriteGate($this->directory . '/gate');
        self::assertFalse($gate->status()['drained']);
        try {
            $gate->mutate(static fn () => self::fail('New writer entered a paused gate.'));
            self::fail('Admission must reject.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('paused', $error->getMessage());
        }
        touch($this->directory . '/continue');
        self::assertSame(0, proc_close($writer));
        self::assertSame(0, proc_close($pause));
        self::assertTrue($gate->status()['drained']);
        self::assertSame('committed reference and bytes', file_get_contents($this->directory . '/committed'));
    }

    public function testTerminatedPauseKeepsAdmissionClosedAfterRestart(): void
    {
        $writer = $this->start('writer');
        $this->waitFor($this->directory . '/admitted');
        $pause = $this->start('pause');
        $this->waitFor($this->directory . '/gate/paused.json');
        proc_terminate($pause, 9);
        proc_close($pause);
        touch($this->directory . '/continue');
        self::assertSame(0, proc_close($writer));
        $gate = new FileWriteGate($this->directory . '/gate');
        self::assertTrue($gate->status()['paused']);
        self::assertFalse($gate->status()['drained']);
        $this->expectException(\RuntimeException::class);
        $gate->mutate(static fn () => null);
    }

    public function testCacheMutationsRespectAdmissionLockAndDrain(): void
    {
        $gateDir = $this->directory . '/gate';
        $cacheDir = $this->directory . '/cache';
        $gate = new FileWriteGate($gateDir);
        $gate->identity();
        $cache = new AppCache($cacheDir, writeGateDir: $gateDir);
        $cache->set(AppCache::NS_SETTINGS, 'original', 'persisted', 60);

        $lock = fopen($gateDir . '/admission.lock', 'r+b');
        self::assertIsResource($lock);
        $pause = null;
        $pauseExit = null;
        try {
            // Hold exclusive admission before a pause marker exists.
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            self::assertFalse($gate->status()['paused']);
            $cache->set(AppCache::NS_SETTINGS, 'contended', 'memory only', 60);
            self::assertNull((new AppCache($cacheDir))->get(AppCache::NS_SETTINGS, 'contended'));
            $cache->invalidateNamespace(AppCache::NS_SETTINGS);
            self::assertSame('persisted', (new AppCache($cacheDir))->get(AppCache::NS_SETTINGS, 'original'));

            // A separate operator publishes the pause before it can acquire admission.
            $pause = $this->start('pause');
            self::assertIsResource($pause);
            $this->waitFor($gateDir . '/paused.json');
            self::assertFalse($gate->status()['drained']);

            $value = $cache->remember(AppCache::NS_SETTINGS, 'during-drain', 60, static fn () => 'fresh');
            self::assertSame('fresh', $value);
            self::assertNull((new AppCache($cacheDir))->get(AppCache::NS_SETTINGS, 'during-drain'));
        } finally {
            // Release admission even if the pause worker inherited the descriptor.
            flock($lock, LOCK_UN);
            fclose($lock);
            if (is_resource($pause)) {
                $pauseExit = proc_close($pause);
            }
        }

        self::assertSame(0, $pauseExit);
        self::assertTrue($gate->status()['drained']);
        $gate->resume(static fn () => null);
        $cache->set(AppCache::NS_SETTINGS, 'after-resume', 'persisted again', 60);
        self::assertSame('persisted again', (new AppCache($cacheDir))->get(AppCache::NS_SETTINGS, 'after-resume'));
    }

    public function testRedirectExitPersistsPendingSessionBeforeDrainCompletes(): void
    {
        $writer = $this->start('session-exit');
        $this->waitFor($this->directory . '/admitted');
        $pause = $this->start('pause');
        $this->waitFor($this->directory . '/gate/paused.json');
        touch($this->directory . '/continue');
        self::assertSame(0, proc_close($writer));
        self::assertSame(0, proc_close($pause));
        self::assertStringContainsString('pending_registration', file_get_contents($this->directory . '/sessions/sess_synthetic'));
        self::assertTrue((new FileWriteGate($this->directory . '/gate'))->status()['drained']);
    }

    private function start(string $mode): mixed
    {
        return proc_open([PHP_BINARY, __DIR__ . '/../../storage/gate-worker.php', $mode, $this->directory],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->directory . '/' . $mode . '.log', 'a'], 2 => ['file', $this->directory . '/' . $mode . '.log', 'a']], $pipes);
    }

    private function waitFor(string $path): void
    {
        $deadline = microtime(true) + 5;
        do {
            clearstatcache(true, $path);
            if (is_file($path)) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        self::fail('Worker did not reach ' . basename($path));
    }
}
