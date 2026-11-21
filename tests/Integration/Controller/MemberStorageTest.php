<?php
namespace Amea\Tests\Integration\Controller;

use PHPUnit\Framework\TestCase;

class MemberStorageTest extends TestCase
{
    public function testMemberDocumentsThroughActualHttpEntryPoints(): void
    {
        $command = ['python3', dirname(__DIR__, 2) . '/http/member-storage.py'];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output);
    }
}
