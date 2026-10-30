<?php
namespace Tests\Integration\Service;

use Amea\Service\JournalMediaService;
use PHPUnit\Framework\TestCase;

final class JournalMediaServiceTest extends TestCase
{
    public function testResolveRejectsTraversalAndSymlinks(): void
    {
        $root = sys_get_temp_dir() . '/amea-journal-' . bin2hex(random_bytes(8));
        $covers = $root . '/uploads/journal/covers';
        mkdir($covers, 0700, true);
        $name = str_repeat('a', 32) . '.jpg';
        $outside = $root . '/outside.jpg';
        copy(__DIR__ . '/../../../assets/img/presidents.jpg', $outside);
        copy($outside, $covers . '/' . $name);
        $media = new JournalMediaService($root);
        try {
            self::assertNotNull($media->resolve('uploads/journal/covers/' . $name));
            self::assertNull($media->resolve('uploads/journal/covers/../' . $name));
            unlink($covers . '/' . $name);
            symlink($outside, $covers . '/' . $name);
            self::assertNull($media->resolve('uploads/journal/covers/' . $name));
            unlink($covers . '/' . $name);
            rmdir($covers);
            symlink($root, $covers);
            self::assertNull($media->resolve('uploads/journal/covers/' . $name));
        } finally {
            if (is_link($covers)) unlink($covers);
            elseif (is_dir($covers)) { @unlink($covers . '/' . $name); rmdir($covers); }
            unlink($outside);
            rmdir($root . '/uploads/journal');
            rmdir($root . '/uploads');
            rmdir($root);
        }
    }
}
