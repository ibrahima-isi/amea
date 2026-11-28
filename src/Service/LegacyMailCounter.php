<?php

declare(strict_types=1);

namespace Amea\Service;

use RuntimeException;

final class LegacyMailCounter
{
    private string $path;

    public function __construct(string $directory, string $date)
    {
        $this->path = $directory . '/.sent-' . str_replace('-', '', $date);
    }

    public function read(): int
    {
        if (!file_exists($this->path) && !is_link($this->path)) {
            return 0;
        }
        $value = is_file($this->path) ? @file_get_contents($this->path) : false;
        if ($value === false || !ctype_digit(trim($value))) {
            throw new RuntimeException('Cannot read legacy daily mail usage.');
        }
        return (int) trim($value);
    }

    /** Callers must hold the file queue lock before writing. */
    public function write(int $usage): void
    {
        $temporary = tempnam(dirname($this->path), '.sent-tmp-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create the daily mail usage file.');
        }
        try {
            $value = (string) $usage;
            if (@file_put_contents($temporary, $value) !== strlen($value)
                || !@chmod($temporary, 0644) || !@rename($temporary, $this->path)) {
                throw new RuntimeException('Cannot persist legacy daily mail usage.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
