<?php

namespace Amea\Storage;

/** Shared key rules for every storage backend. */
trait ValidatesStorageKeys
{
    private function validateKey(string $key): void
    {
        if (strlen($key) > 1024 || !mb_check_encoding($key, 'UTF-8') || !str_starts_with($key, 'uploads/') || str_contains($key, '\\') || preg_match('/[\x00-\x1f\x7f]/', $key)) {
            throw new StorageException('Invalid storage key.');
        }
        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new StorageException('Invalid storage key.');
            }
        }
    }
}
