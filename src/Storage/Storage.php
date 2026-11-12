<?php

namespace Amea\Storage;

interface Storage
{
    /** @return resource */
    public function read(string $key): mixed;

    /** @param resource $source
     * @return array{size: int, sha256: string} */
    public function write(string $key, mixed $source, string $mime, int $createdAt): array;

    /** @return array{size: int, mime: string, createdAt: int, sha256: ?string}|null */
    public function stat(string $key): ?array;

    public function delete(string $key): void;
    public function copy(string $sourceKey, string $destinationKey): void;

    /** @return array{objects: list<array{key: string, size: int, createdAt: int}>, cursor: ?string} */
    public function listPage(string $prefix, ?string $cursor, int $limit): array;
}
