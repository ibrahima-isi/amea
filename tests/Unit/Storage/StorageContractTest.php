<?php

namespace Amea\Tests\Unit\Storage;

use Amea\Storage\LocalStorage;
use Amea\Storage\Storage;
use PHPUnit\Framework\TestCase;

class StorageContractTest extends TestCase
{
    protected Storage $storage;
    protected string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/amea-storage-' . bin2hex(random_bytes(8));
        mkdir($this->root);
        $this->storage = new LocalStorage($this->root);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->root);
        }
    }

    protected function input(string $contents): mixed
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);
        return $stream;
    }

    public function testAcceptedContentCanBeReadWithOriginalAge(): void
    {
        $key = 'uploads/students/cvs/example.pdf';
        $written = $this->storage->write($key, $this->input("%PDF-1.7\nexample\n"), 'application/pdf', 1762862400);
        $output = $this->storage->read($key);
        self::assertSame("%PDF-1.7\nexample\n", stream_get_contents($output));
        fclose($output);
        self::assertSame(['size' => 17, 'mime' => 'application/pdf', 'createdAt' => 1762862400, 'sha256' => $written['sha256']], $this->storage->stat($key));
    }
    public function testWritesAndCopiesAreRetrySafeAndRejectConflictingContent(): void
    {
        $key = 'uploads/staging/retry.txt';
        $first = $this->storage->write($key, $this->input('first'), 'text/plain', 1000);
        self::assertSame($first, $this->storage->write($key, $this->input('first'), 'text/plain', 2000));
        $this->storage->copy($key, 'uploads/final/retry.txt');
        $this->storage->copy($key, 'uploads/final/retry.txt');
        self::assertSame(1000, $this->storage->stat('uploads/final/retry.txt')['createdAt']);
        try {
            $this->storage->write($key, $this->input('other'), 'text/plain', 3000);
            self::fail('Conflicting writes must fail.');
        } catch (\Amea\Storage\StorageException) {
            self::assertSame('first', stream_get_contents($this->storage->read($key)));
        }
        $this->storage->write('uploads/conflict.txt', $this->input('conflict'), 'text/plain', 1000);
        $this->expectException(\Amea\Storage\StorageException::class);
        $this->storage->copy($key, 'uploads/conflict.txt');
    }

    public function testMissingReadsFailWhileMissingStatAndDoubleDeleteSucceed(): void
    {
        $key = 'uploads/missing.txt';
        self::assertNull($this->storage->stat($key));
        $this->storage->delete($key);
        $this->storage->delete($key);
        $this->storage->write($key, $this->input('temporary'), 'text/plain', 1000);
        $this->storage->delete($key);
        self::assertNull($this->storage->stat($key));
        $this->expectException(\Amea\Storage\StorageException::class);
        $this->storage->read($key);
    }

    public function testPaginationPreservesFullKeysAndOriginalLifecycleAge(): void
    {
        foreach (['b/same.txt', 'a/same.txt', 'c/third.txt', 'd/fourth.txt', 'e/fifth.txt'] as $name) {
            $this->storage->write('uploads/list/' . $name, $this->input($name), 'text/plain', 1234);
        }
        $this->storage->write('uploads/unrelated.txt', $this->input('other'), 'text/plain', 4567);
        $objects = [];
        $cursor = null;
        do {
            $page = $this->storage->listPage('uploads/list/', $cursor, 2);
            self::assertLessThanOrEqual(2, count($page['objects']));
            $objects = array_merge($objects, $page['objects']);
            $cursor = $page['cursor'];
        } while ($cursor !== null);
        self::assertSame(['uploads/list/a/same.txt', 'uploads/list/b/same.txt', 'uploads/list/c/third.txt', 'uploads/list/d/fourth.txt', 'uploads/list/e/fifth.txt'], array_column($objects, 'key'));
        self::assertSame([1234, 1234, 1234, 1234, 1234], array_column($objects, 'createdAt'));
        self::assertCount(5, $this->storage->listPage('uploads/list', null, 100)['objects']);
        self::assertCount(6, $this->storage->listPage('', null, 100)['objects']);
        self::assertSame(['objects' => [], 'cursor' => null], $this->storage->listPage('uploads/absent/', null, 2));
    }

    public function testEmptyAndLargeStreamsRoundTripWithinABoundedMemoryBudget(): void
    {
        $empty = $this->storage->write('uploads/empty.txt', $this->input(''), 'text/plain', 1000);
        self::assertSame(['size' => 0, 'sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'], $empty);
        $source = tmpfile();
        $chunk = str_repeat('abcdef0123456789', 4096);
        for ($index = 0; $index < 1536; $index++) {
            fwrite($source, $chunk);
        }
        rewind($source);
        $baseline = memory_get_usage(true);
        memory_reset_peak_usage();
        $written = $this->storage->write('uploads/large.bin', $source, 'application/octet-stream', 1000);
        fclose($source);
        $this->storage->copy('uploads/large.bin', 'uploads/copied-large.bin');
        $output = $this->storage->read('uploads/copied-large.bin');
        $hash = hash_init('sha256');
        $bytes = hash_update_stream($hash, $output);
        fclose($output);
        self::assertSame(100663296, $bytes);
        self::assertSame($written['sha256'], hash_final($hash));
        self::assertLessThan(16777216, memory_get_peak_usage(true) - $baseline);
    }

    public static function invalidKeys(): array
    {
        return array_map(static fn ($key) => [$key], ['', '/uploads/file', 'uploads', 'uploads/', 'uploads/../outside', 'uploads/./file', 'uploads//file', 'uploads/file/', 'uploads\\outside', 'other/file', "uploads/nu\0ll", "uploads/line\nfile", "uploads/\xff", 'uploads/' . str_repeat('a', 1025)]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidKeys')]
    public function testInvalidKeysAreRejectedByEveryOperation(string $key): void
    {
        $this->storage->write('uploads/valid', $this->input('valid'), 'text/plain', 1000);
        foreach ([fn () => $this->storage->read($key), fn () => $this->storage->stat($key), fn () => $this->storage->delete($key), fn () => $this->storage->write($key, $this->input('x'), 'text/plain', 1000), fn () => $this->storage->copy('uploads/valid', $key)] as $operation) {
            try {
                $operation();
                self::fail('Invalid storage keys must fail.');
            } catch (\Amea\Storage\StorageException) {
                self::assertTrue(true);
            }
        }
    }

    public function testLocalSymlinksAndNonRegularObjectsCannotEscapeTheManagedRoots(): void
    {
        if (!$this->storage instanceof LocalStorage) {
            self::markTestSkipped('Local filesystem safety is specific to the local backend.');
        }
        $outside = $this->root . '/outside';
        mkdir($outside);
        file_put_contents($outside . '/secret', 'private');
        mkdir($this->root . '/uploads');
        symlink($outside, $this->root . '/uploads/linked');
        foreach ([fn () => $this->storage->stat('uploads/linked/secret'), fn () => $this->storage->read('uploads/linked/secret'), fn () => $this->storage->delete('uploads/linked/secret'), fn () => $this->storage->write('uploads/linked/new', $this->input('x'), 'text/plain', 1), fn () => $this->storage->listPage('', null, 10)] as $operation) {
            try {
                $operation();
                self::fail('Local symlinks must be rejected.');
            } catch (\Amea\Storage\StorageException) {
                self::assertSame('private', file_get_contents($outside . '/secret'));
            }
        }
        unlink($this->root . '/uploads/linked');
        mkdir($this->root . '/uploads/directory');
        try {
            $this->storage->stat('uploads/directory');
            self::fail('A directory is not a storage object.');
        } catch (\Amea\Storage\StorageException) {
            self::assertDirectoryExists($this->root . '/uploads/directory');
        }
        unlink($this->root . '/storage/object-storage/.lock');
        rmdir($this->root . '/storage/object-storage');
        symlink($outside, $this->root . '/storage/object-storage');
        $this->expectException(\Amea\Storage\StorageException::class);
        $this->storage->write('uploads/cannot-write.txt', $this->input('x'), 'text/plain', 1);
    }

    public function testLocalPermissionFailuresAreExplicitAndCannotPublishPartialObjects(): void
    {
        if (!$this->storage instanceof LocalStorage) {
            self::markTestSkipped('Local permissions are specific to the local backend.');
        }
        $this->storage->write('uploads/locked/file.txt', $this->input('original'), 'text/plain', 1);
        chmod($this->root . '/uploads/locked', 0000);
        try {
            try {
                $this->storage->stat('uploads/locked/file.txt');
                self::fail('An inaccessible object must not appear missing.');
            } catch (\Amea\Storage\StorageException) {
                self::assertTrue(true);
            }
        } finally {
            chmod($this->root . '/uploads/locked', 0770);
        }
        chmod($this->root . '/storage/object-storage', 0500);
        try {
            try {
                $this->storage->write('uploads/failed.txt', $this->input('new'), 'text/plain', 1);
                self::fail('Metadata failure must fail the write.');
            } catch (\Amea\Storage\StorageException) {
                self::assertFileDoesNotExist($this->root . '/uploads/failed.txt');
            }
        } finally {
            chmod($this->root . '/storage/object-storage', 0770);
        }
    }

    public function testNonSeekableAndFailedInputStreamsCannotPublishPartialContent(): void
    {
        if (!in_array('storageinput', stream_get_wrappers(), true)) {
            stream_wrapper_register('storageinput', NonSeekableStream::class);
        }
        NonSeekableStream::$fail = false;
        $source = fopen('storageinput://success', 'rb');
        try {
            self::assertSame(16, $this->storage->write('uploads/stream.txt', $source, 'text/plain', 1)['size']);
            self::assertSame('streamed content', stream_get_contents($this->storage->read('uploads/stream.txt')));
        } finally {
            fclose($source);
        }
        NonSeekableStream::$fail = true;
        $source = fopen('storageinput://failure', 'rb');
        try {
            try {
                $this->storage->write('uploads/failed-stream.txt', $source, 'text/plain', 1);
                self::fail('Input read failures must abort the write.');
            } catch (\Amea\Storage\StorageException) {
                self::assertNull($this->storage->stat('uploads/failed-stream.txt'));
            }
        } finally {
            NonSeekableStream::$fail = false;
            fclose($source);
        }
    }

    public function testInvalidSourcesMetadataAndListingArgumentsFail(): void
    {
        $operations = [
            fn () => $this->storage->write('uploads/invalid', 'string', 'text/plain', 1),
            fn () => $this->storage->write('uploads/invalid', $this->input('x'), "text/plain\nInjected: x", 1),
            fn () => $this->storage->write('uploads/invalid', $this->input('x'), '', 1),
            fn () => $this->storage->write('uploads/invalid', $this->input('x'), 'text/plain', -1),
            fn () => $this->storage->listPage('uploads//', null, 10),
            fn () => $this->storage->listPage('uploads/../', null, 10),
            fn () => $this->storage->listPage('', null, 0),
            fn () => $this->storage->listPage('', null, 1001),
            fn () => $this->storage->listPage('', 'invalid-cursor', 10),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('Invalid storage arguments must be rejected.');
            } catch (\Amea\Storage\StorageException) {
                self::assertNull($this->storage->stat('uploads/invalid'));
            }
        }
    }

    protected function concurrentEnvironment(): array
    {
        return ['STORAGE_DRIVER' => 'local'];
    }

    public function testConcurrentConflictingWritersPublishExactlyOneObject(): void
    {
        $coordination = sys_get_temp_dir() . '/amea-storage-race-' . bin2hex(random_bytes(8));
        mkdir($coordination);
        $children = [];
        $results = [];
        try {
            foreach (['first', 'second'] as $content) {
                $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/storage/race-writer.php', $this->root ?? sys_get_temp_dir(), $content, $coordination], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), $this->concurrentEnvironment()));
                if (!is_resource($process)) {
                    throw new \RuntimeException('Unable to start concurrent storage writer.');
                }
                fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            touch($coordination . '/start');
            foreach ($children as [$process, $pipes]) {
                $results[] = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $error);
            }
            sort($results);
            self::assertSame(['accepted', 'conflict'], $results);
            $accepted = stream_get_contents($this->storage->read('uploads/raced.txt'));
            self::assertContains($accepted, ['first', 'second']);
            self::assertSame(hash('sha256', $accepted), $this->storage->stat('uploads/raced.txt')['sha256']);
        } finally {
            foreach (glob($coordination . '/*') as $file) {
                unlink($file);
            }
            rmdir($coordination);
        }
    }
}
