<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Cache;

use Amea\Cache\AppCache;
use PHPUnit\Framework\TestCase;

final class AppCacheTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/amea-appcache-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->tmpDir);
    }

    public function testFileCachePersistsAcrossInstancesAndInvalidatesByNamespace(): void
    {
        $cacheDir = $this->tmpDir . '/cache';
        $writer = new AppCache($cacheDir, 'scope-a');
        $calls = 0;
        $value = $writer->remember('settings', 'all', 300, static function () use (&$calls): array {
            $calls++;
            return ['contact_email' => 'contact@aeesgs.org'];
        });
        self::assertSame(['contact_email' => 'contact@aeesgs.org'], $value);
        self::assertSame(1, $calls);

        $writer->set('slider', 'active', [['id' => 1]], 300);

        // Fresh instance reads from disk without re-invoking loader.
        $reader = new AppCache($cacheDir, 'scope-a');
        self::assertSame(['contact_email' => 'contact@aeesgs.org'], $reader->get('settings', 'all'));
        self::assertSame([['id' => 1]], $reader->get('slider', 'active'));
        self::assertSame(2, $reader->stats()['entries']);

        // Different scope in the same directory is completely isolated.
        $otherScope = new AppCache($cacheDir, 'scope-b');
        self::assertNull($otherScope->get('settings', 'all'));

        // Namespace invalidation clears only the targeted namespace.
        $writer->invalidateNamespace('slider');
        $readerAfter = new AppCache($cacheDir, 'scope-a');
        self::assertNull($readerAfter->get('slider', 'active'));
        self::assertSame(['contact_email' => 'contact@aeesgs.org'], $readerAfter->get('settings', 'all'));

        // Clear removes remaining entries for this scope.
        self::assertGreaterThanOrEqual(1, $writer->clear());
        self::assertSame(0, (new AppCache($cacheDir, 'scope-a'))->stats()['entries']);
    }

    public function testInvalidationDuringLoadDiscardsStaleResult(): void
    {
        $reader = new AppCache($this->tmpDir);
        $writer = new AppCache($this->tmpDir);
        $calls = 0;
        $value = $reader->remember('settings', 'all', 60, function () use ($writer, &$calls) {
            if (++$calls === 1) {
                $writer->invalidateNamespace('settings');
                return 'old';
            }
            return 'new';
        });
        self::assertSame('new', $value);
        self::assertNull($writer->get('settings', 'all'));
    }

    public function testCorruptedJsonEntryFailsOpenAndRecovers(): void
    {
        $cacheDir = $this->tmpDir . '/corrupt';
        $cache = new AppCache($cacheDir, 'scope-c');
        $cache->set('journal', 'recent', ['ok'], 300);
        $files = glob($cacheDir . '/*.json') ?: [];
        self::assertCount(1, $files);
        file_put_contents($files[0], '{not-valid-json');

        $fresh = new AppCache($cacheDir, 'scope-c');
        self::assertNull($fresh->get('journal', 'recent'));
        self::assertFileDoesNotExist($files[0]);
    }

    public function testWriteGatePausePreventsDiskCacheWritesAndCorruptCleanup(): void
    {
        $cacheDir = $this->tmpDir . '/paused-cache';
        $gateDir = $this->tmpDir . '/write-gate';
        mkdir($gateDir, 0700, true);
        file_put_contents($gateDir . '/paused.json', '{"paused":true,"drained":true}');

        $cache = new AppCache($cacheDir, 'scope-paused', $gateDir);
        $cache->set(AppCache::NS_SETTINGS, 'all', ['a' => 'b'], 300);

        // In-process memory read still works, but disk must remain untouched while paused.
        self::assertSame(['a' => 'b'], $cache->get(AppCache::NS_SETTINGS, 'all'));
        self::assertDirectoryDoesNotExist($cacheDir);

        // Pre-existing corrupt file must NOT be unlinked while write-gate is paused.
        $existingDir = $this->tmpDir . '/paused-corrupt';
        $writer = new AppCache($existingDir, 'scope-paused-2');
        $writer->set(AppCache::NS_JOURNAL, 'recent', ['ok'], 300);
        $files = glob($existingDir . '/*.json') ?: [];
        self::assertCount(1, $files);
        file_put_contents($files[0], '{corrupt-while-paused');

        $pausedReader = new AppCache($existingDir, 'scope-paused-2', $gateDir);
        self::assertNull($pausedReader->get(AppCache::NS_JOURNAL, 'recent'));
        self::assertFileExists($files[0]);
    }
}
