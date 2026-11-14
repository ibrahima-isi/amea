<?php

namespace Amea\Storage\Migration;

use Amea\Storage\Storage;
use Amea\Storage\StorageException;

/** Retains every source and existing destination, including on partial failure. */
final class Copier
{
    public function __construct(private Storage $source, private Storage $destination, private array $identity, private Inventory $inventory)
    {
        Manifest::assertBackendPair($source, $destination);
        if (Manifest::backendIdentity($source) !== ($identity['source'] ?? null)
            || Manifest::backendIdentity($destination) !== ($identity['destination'] ?? null)
            || $identity['source'] === $identity['destination']) {
            throw new StorageException('Transfer backend identities do not match the selected targets.');
        }
    }

    public function copy(Manifest $manifest, array $mappings = [], ?string $reviewedMappings = null): void
    {
        $manifest->assertIdentity($this->identity);
        if ($mappings !== [] && $reviewedMappings !== hash('sha256', json_encode($mappings, JSON_THROW_ON_ERROR))) {
            throw new StorageException('Explicit full-key mappings require their reviewed SHA-256 identity.');
        }
        $manifest->locked(function (Manifest $manifest) use ($mappings, $reviewedMappings) {
            $this->reconcile($manifest, $this->inventory->scan());
            $mapped = [];
            foreach ($mappings as $mapping) {
                if (!is_string($mapping['source_key'] ?? null) || isset($mapped[$mapping['source_key']])) {
                    throw new StorageException('Duplicate or invalid source mapping.');
                }
                $mapped[$mapping['source_key']] = true;
                $manifest->map($mapping['source_key'] ?? '', $mapping['destination_key'] ?? '', $mapping['expected_sha256'] ?? '');
            }
            if ($mappings !== []) {
                $manifest->history[] = ['type' => 'reviewed-mapping', 'sha256' => $reviewedMappings, 'mappings' => $mappings, 'at' => time()];
            }
            $manifest->checkpoint();
            foreach ($manifest->files as $key => $file) {
                if (($file['source_state'] ?? 'present') === 'removed' || !$file['valid_key']) {
                    continue;
                }
                $input = $this->source->read($key);
                $spool = tmpfile();
                if ($spool === false) {
                    fclose($input);
                    throw new StorageException('Unable to allocate the migration transfer spool.');
                }
                try {
                    if (stream_copy_to_stream($input, $spool) === false) {
                        throw new StorageException('Unable to spool complete source bytes.');
                    }
                    rewind($spool);
                    $fingerprint = Inventory::fingerprint($spool);
                    $this->assertBytes($file, $fingerprint);
                    rewind($spool);
                    $existing = $this->destination->stat($file['destination_key']);
                    if ($existing === null) {
                        $this->destination->write($file['destination_key'], $spool, $file['mime'], $file['created_at']);
                    }
                    // Every retry and every newly accepted write is independently read back.
                    $this->assertBytes($file, $this->destinationFingerprint($file['destination_key']));
                    $metadata = $this->destination->stat($file['destination_key']);
                    if ($metadata === null || $metadata['createdAt'] !== $file['created_at']) {
                        throw new StorageException('Destination original lifecycle timestamp differs.');
                    }
                    $manifest->files[$key]['copy_state'] = 'copied';
                    $manifest->files[$key]['verify_state'] = 'verified';
                    $manifest->checkpoint();
                } finally {
                    fclose($input);
                    fclose($spool);
                }
            }
            $changed = $this->reconcile($manifest, $this->inventory->scan());
            $manifest->checkpoint();
            if ($changed) {
                throw new StorageException('Source changed during transfer. Review reconciliation and rerun.');
            }
        });
    }

    /** Full-key differences, including retained removed files and unresolved references. */
    public function verify(Manifest $manifest): array
    {
        $manifest->assertIdentity($this->identity);
        return $manifest->locked(function (Manifest $manifest) {
            $this->reconcile($manifest, $this->inventory->scan());
            $differences = array_values(array_filter($manifest->issues, static fn ($issue) => empty($issue['disposition'])));
            foreach ($manifest->files as $key => $file) {
                if (($file['source_state'] ?? 'present') === 'removed' && empty($file['disposition'])) {
                    $differences[] = ['type' => 'removed-source', 'key' => $key];
                    continue;
                }
                if (!$file['valid_key']) {
                    if (empty($file['disposition'])) {
                        $differences[] = ['type' => 'invalid-key', 'key' => $key];
                    }
                    continue;
                }
                try {
                    if ($this->destination->stat($file['destination_key']) === null) {
                        $manifest->files[$key]['verify_state'] = 'missing';
                        $differences[] = ['type' => 'missing-destination', 'key' => $file['destination_key']];
                        continue;
                    }
                    $this->assertBytes($file, $this->destinationFingerprint($file['destination_key']));
                    if ($this->destination->stat($file['destination_key'])['createdAt'] !== $file['created_at']) {
                        throw new StorageException('Destination original lifecycle timestamp differs.');
                    }
                    $manifest->files[$key]['verify_state'] = 'verified';
                } catch (StorageException $error) {
                    $manifest->files[$key]['verify_state'] = 'failed';
                    $differences[] = ['type' => 'unverified-destination', 'key' => $file['destination_key']];
                }
            }
            foreach ($manifest->references as $reference) {
                if (($reference['state'] ?? '') !== 'resolved' && empty($reference['disposition'])) {
                    $differences[] = ['type' => 'unresolved-reference', 'reference' => $reference];
                }
            }
            $manifest->checkpoint();
            return $differences;
        });
    }

    private function destinationFingerprint(string $key): array
    {
        $stream = $this->destination->read($key);
        try {
            return Inventory::fingerprint($stream);
        } finally {
            fclose($stream);
        }
    }

    private function assertBytes(array $file, array $fingerprint): void
    {
        if ($file['size'] !== $fingerprint['size'] || !hash_equals($file['sha256'], $fingerprint['sha256'])) {
            throw new StorageException('Object content differs from the inventoried bytes.');
        }
    }

    private function reconcile(Manifest $manifest, Manifest $current): bool
    {
        $current->assertIdentity($this->identity);
        $changed = false;
        foreach ($current->files as $key => $file) {
            $before = $manifest->files[$key] ?? null;
            if ($before === null) {
                $manifest->files[$key] = $file;
                $manifest->history[] = ['type' => 'added', 'key' => $key, 'at' => time()];
                $changed = true;
            } elseif ($before['sha256'] !== $file['sha256'] || $before['size'] !== $file['size']
                || $before['created_at'] !== $file['created_at'] || ($before['source_state'] ?? 'present') === 'removed') {
                $manifest->history[] = ['type' => 'changed', 'at' => time(), 'file' => $before];
                $file['destination_key'] = $before['destination_key'];
                $manifest->files[$key] = $file;
                $changed = true;
            }
        }
        foreach ($manifest->files as $key => $file) {
            if (!isset($current->files[$key]) && ($file['source_state'] ?? 'present') !== 'removed') {
                $manifest->history[] = ['type' => 'removed', 'at' => time(), 'file' => $file];
                $manifest->files[$key]['source_state'] = 'removed';
                $manifest->files[$key]['verify_state'] = 'pending-disposition';
                $changed = true;
            }
        }
        // Keep unresolved special entries even if removed after the first scan.
        foreach ($current->issues as $issue) {
            $known = false;
            foreach ($manifest->issues as $existing) {
                if ($existing['type'] === $issue['type'] && $existing['key'] === $issue['key']) {
                    $known = true;
                }
            }
            if (!$known) {
                $manifest->issues[] = $issue;
            }
        }
        return $changed;
    }
}
