<?php

namespace Amea\Tests\Unit\Storage;

/** A read-only external stream fixture with optional mid-read failure. */
class NonSeekableStream
{
    public mixed $context;
    public static bool $fail = false;
    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string|false
    {
        if (self::$fail && $this->position > 0) {
            return false;
        }
        $part = substr('streamed content', $this->position, min($count, 4));
        $this->position += strlen($part);
        return $part;
    }

    public function stream_eof(): bool
    {
        return $this->position >= 16;
    }

    public function stream_stat(): array
    {
        return [];
    }
}
