<?php

namespace Amea\Tests\Integration\Storage;

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
