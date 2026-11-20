<?php

namespace Amea\Storage\Migration;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Storage;
use Amea\Storage\StorageException;
use PDO;

/** Binds reopening to fresh bytes, references, checkpoint and actual role reports. */
final class Cutover
{
    public function __construct(private FileWriteGate $gate, private PDO $db, private Storage $source,
        private Storage $destination, private array $identity, private string $manifestPath)
    {
    }

    public function pause(): array
    {
        return $this->gate->pause($this->identity);
    }

    public function finalCheck(array $targets): array
    {
        return $this->gate->exclusive(function (array $state) use ($targets) {
            $this->assertSelection($state);
            $proof = $this->verify($targets, $state);
            $state['verification'] = $proof;
            $state['verified_at'] = microtime(true);
            $this->gate->record($state);
            return $proof;
        });
    }

    public function resume(array $targets): void
    {
        $this->gate->resume(function (array $state) use ($targets) {
            $this->assertSelection($state);
            $before = $state['verification'] ?? null;
            if (!is_array($before) || empty($before['backup']) || $before['checkpoint'] !== $this->checkpointIdentity()) {
                throw new StorageException('Final verification is absent or its finalized checkpoint changed.');
            }
            if ($before !== $this->verify($targets, $state)) {
                throw new StorageException('Manifest, current references or actual target configuration changed after final-check.');
            }
        });
    }

    public static function sessionStoreIdentity(string $path): string
    {
        if ($path === '' || realpath($path) !== $path || !is_dir($path) || is_link($path)) {
            throw new StorageException('Select a canonical session directory explicitly.');
        }
        $stat = stat($path);
        return hash('sha256', json_encode([$stat['dev'], $stat['ino']], JSON_THROW_ON_ERROR));
    }

    public static function codeIdentity(string $root): string
    {
        $files = [];
        foreach (['src', 'config', 'functions'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[substr($file->getPathname(), strlen($root) + 1)] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        ksort($files);
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    public static function sessionStores(array $identity): array
    {
        $path = $identity['sessions'];
        return match ($identity['topology'] ?? 'split') {
            'single' => ['app' => $path],
            'split' => ['public' => $path . '/public', 'staff' => $path . '/staff'],
            default => throw new StorageException('Unknown migration topology.'),
        };
    }

    public static function targetIdentity(array $targets, array $state, string $backend, string $database,
        string $serializer, FileWriteGate $gate, array $sessionStores): string
    {
        $roles = array_keys($sessionStores) === ['app'] ? ['app'] : ['jobs', 'public', 'staff'];
        $normalized = [];
        foreach ($targets as $target) {
            $role = $target['role'] ?? '';
            if (!in_array($role, $roles, true) || isset($normalized[$role])
                || ($target['challenge'] ?? '') !== $state['challenge']
                || !is_numeric($target['observed_at'] ?? null) || $target['observed_at'] < $state['paused_at']
                || $target['observed_at'] > microtime(true) + 5 || $target['observed_at'] < microtime(true) - 300
                || ($target['backend'] ?? '') !== $backend
                || ($target['database'] ?? '') !== $database
                || ($target['gate'] ?? '') !== $gate->identity()
                || ($target['serializer'] ?? '') !== $serializer
                || ($target['code'] ?? '') !== self::codeIdentity(dirname(__DIR__, 3))) {
                throw new StorageException('Fresh deployment reports must select the verified database, backend, gate and code.');
            }
            if ($role !== 'jobs' && ($target['session_store'] ?? '') !== self::sessionStoreIdentity($sessionStores[$role] ?? '')) {
                throw new StorageException('Actual session stores differ from the stores selected for audit.');
            }
            unset($target['observed_at'], $target['challenge']);
            ksort($target);
            $normalized[$role] = $target;
        }
        ksort($normalized);
        if (array_keys($normalized) !== $roles) {
            throw new StorageException('Reports for every selected deployment role are required.');
        }
        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function verify(array $targets, array $state): array
    {
        // Empty ledger is the deliberate cutover policy. No intent can change owner.
        if ((int)$this->db->query('SELECT COUNT(*) FROM file_operations')->fetchColumn() !== 0) {
            throw new StorageException('Pending file intents must finish on their owning source backend before cutover.');
        }
        $targetIdentity = self::targetIdentity($targets, $state, $this->identity['destination'],
            $this->identity['database'], $this->identity['serializer'], $this->gate,
            self::sessionStores($this->identity));
        $manifest = Manifest::load($this->manifestPath, $this->identity);
        $auditor = new ReferenceAudit($this->db, $this->destination, $this->identity,
            $this->identity['sessions'], $this->identity['serializer'], 'destination');
        $manifest->locked(function (Manifest $manifest) use ($auditor) {
            $auditor->audit($manifest);
            $manifest->checkpoint();
        });
        $inventory = new Inventory($this->source, $this->identity,
            $this->source instanceof LocalStorage ? $this->source->migrationRoot() : null);
        $differences = (new Copier($this->source, $this->destination, $this->identity, $inventory))->verify($manifest);
        if ($differences !== []) {
            throw new StorageException('Final verification has unresolved bytes, references or inventory exceptions.');
        }
        $backup = $state['backup'] ?? null;
        if ($backup !== null && ($backup['manifest_sha256'] !== $manifest->reviewIdentity()
            || $backup['checkpoint_sha256'] !== $this->checkpointIdentity())) {
            throw new StorageException('The coordinated backup must match this finalized manifest and checkpoint.');
        }
        if ($backup !== null) {
            $snapshot = new RecoverySnapshot($this->gate);
            $snapshot->assertDatabaseState($this->db, $backup['database_sha256']);
            $snapshot->assertSessionState(self::sessionStores($this->identity), $backup['sessions']);
        }
        return ['manifest' => $manifest->reviewIdentity(), 'checkpoint' => $this->checkpointIdentity(),
            'targets' => $targetIdentity, 'backup' => $backup['sha256'] ?? null];
    }

    private function checkpointIdentity(): string
    {
        Manifest::assertPrivatePath($this->manifestPath . '.checkpoint');
        $digest = @hash_file('sha256', $this->manifestPath . '.checkpoint');
        if ($digest === false) {
            throw new StorageException('A finalized checkpoint is required.');
        }
        return $digest;
    }

    private function assertSelection(array $state): void
    {
        if (($state['selection'] ?? null) !== $this->identity || empty($state['drained'])) {
            throw new StorageException('Completed drain and selected migration identities are required.');
        }
    }
}
