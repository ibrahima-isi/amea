<?php

declare(strict_types=1);

namespace Amea\Command;

final class JobLog
{
    public static function write(string $message): void
    {
        fwrite(STDOUT, '[' . date('c') . '] ' . $message . PHP_EOL);
    }
}
