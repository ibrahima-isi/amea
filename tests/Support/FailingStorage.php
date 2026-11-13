<?php
namespace Amea\Tests\Support;

use Amea\Storage\Storage;
use Amea\Storage\StorageException;

/** Inject faults only at the external object-store boundary. */
class FailingStorage implements Storage
{
    public int $copiesUntilFailure = -1;
    public bool $failDelete = false;
    public int $deletesUntilFailure = -1;
    public bool $failWrite = false;
    public bool $failStat = false;
    public bool $failRead = false;
    public bool $failList = false;
    public function __construct(public Storage $inner) {}
    public function failNextWrite(): void { $this->failWrite = true; }
    public function read(string $key): mixed { if ($this->failRead) { throw new StorageException('Injected read failure'); } return $this->inner->read($key); }
    public function stat(string $key): ?array { if ($this->failStat) { throw new StorageException('Injected metadata failure'); } return $this->inner->stat($key); }
    public function write(string $key, mixed $source, string $mime, int $createdAt): array
    {
        if ($this->failWrite) { $this->failWrite = false; throw new StorageException('Injected write failure'); }
        return $this->inner->write($key, $source, $mime, $createdAt);
    }
    public function copy(string $sourceKey, string $destinationKey): void
    {
        if ($this->copiesUntilFailure === 0) { $this->copiesUntilFailure = -1; throw new StorageException('Injected copy failure'); }
        $this->copiesUntilFailure--;
        $this->inner->copy($sourceKey, $destinationKey);
    }
    public function delete(string $key): void
    {
        if ($this->failDelete || $this->deletesUntilFailure === 0) { $this->deletesUntilFailure = -1; throw new StorageException('Injected delete failure'); }
        $this->deletesUntilFailure--;
        $this->inner->delete($key);
    }
    public function listPage(string $prefix, ?string $cursor, int $limit): array { if ($this->failList) { throw new StorageException('Injected listing failure'); } return $this->inner->listPage($prefix, $cursor, $limit); }
}
