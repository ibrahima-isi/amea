<?php

namespace Amea\Storage\Migration;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Storage;
use Amea\Storage\StorageException;
use PDO;

/** Private coordinated snapshots. Restoration always targets a separate empty DB. */
final class RecoverySnapshot
{
    public function __construct(private FileWriteGate $gate) {}

    public function assertDatabaseState(PDO $db, string $expected): void
    {
        if (!hash_equals($expected, $this->databaseFingerprint($db))) {
            throw new StorageException('Current database no longer matches the coordinated recovery point.');
        }
    }

    public function assertSessionState(array $sessions, array $expected): void
    {
        foreach ($sessions as $role => $path) {
            if (($expected[$role] ?? '') !== $this->treeIdentity($path)) {
                throw new StorageException('Current session state no longer matches the coordinated recovery point.');
            }
        }
    }

    public function capture(string $directory, PDO $db, Storage $backend, string $applicationBackend,
        array $sessions, string $persistentStorage, string $manifestPath, array $configuration): array
    {
        return $this->gate->exclusive(function (array $state) use ($directory, $db, $backend, $applicationBackend,
            $sessions, $persistentStorage, $manifestPath, $configuration) {
            if (file_exists($directory) || !str_starts_with($directory, '/') || !mkdir($directory, 0700)) {
                throw new StorageException('A backup requires a fresh private absolute directory.');
            }
            Manifest::assertPrivatePath($directory . '/snapshot.json');
            $started = microtime(true);
            $this->assertLedgerOwner($db, $applicationBackend);
            Manifest::assertPrivatePath($manifestPath);
            $header = json_decode(strtok(file_get_contents($manifestPath), "\n"), true, 512, JSON_THROW_ON_ERROR);
            $reviewed = Manifest::load($manifestPath, $header['identity'] ?? []);
            if ($reviewed->identity['database'] !== ReferenceAudit::databaseIdentity($db)
                || !in_array(Manifest::backendIdentity($backend), [$reviewed->identity['source'], $reviewed->identity['destination']], true)
                || $reviewed->identity['serializer'] !== ($configuration['serializer'] ?? '')
                || $sessions !== Cutover::sessionStores($reviewed->identity)) {
                throw new StorageException('Recovery references require the reviewed database, backend and session identity.');
            }
            $reviewed->locked(function () use ($directory, $manifestPath) {
                foreach (['manifest' => $manifestPath, 'checkpoint' => $manifestPath . '.checkpoint'] as $name => $path) {
                    Manifest::assertPrivatePath($path);
                    if (!copy($path, $directory . '/' . $name) || !chmod($directory . '/' . $name, 0600)) {
                        throw new StorageException('Unable to preserve finalized migration evidence.');
                    }
                }
            });
            $reviewed = Manifest::load($directory . '/manifest', $reviewed->identity, $directory . '/checkpoint');
            $this->assertReferences($db, $backend, $sessions, $configuration['serializer'], $reviewed);
            $database = $directory . '/database.jsonl';
            $this->dumpDatabase($db, $database);
            $objects = [];
            mkdir($directory . '/objects', 0700);
            $cursor = null;
            do {
                $page = $backend->listPage('uploads/', $cursor, 500);
                foreach ($page['objects'] as $object) {
                    $key = $object['key'];
                    $metadata = $backend->stat($key);
                    if ($metadata === null || isset($objects[$key])) {
                        throw new StorageException('Object inventory changed or returned duplicate keys during backup.');
                    }
                    $stream = $backend->read($key);
                    $path = $directory . '/objects/' . hash('sha256', $key);
                    try {
                        $fingerprint = $this->copyStream($stream, $path);
                    } finally {
                        fclose($stream);
                    }
                    if ($fingerprint['size'] !== $metadata['size']
                        || ($metadata['sha256'] !== null && $fingerprint['sha256'] !== $metadata['sha256'])) {
                        throw new StorageException('Backup bytes differ from the selected object metadata.');
                    }
                    $objects[$key] = array_merge($fingerprint, ['mime' => $metadata['mime'], 'created_at' => $metadata['createdAt'],
                        'file' => 'objects/' . basename($path)]);
                }
                $cursor = $page['cursor'];
            } while ($cursor !== null);
            ksort($objects);
            $trees = [];
            foreach ($sessions as $role => $path) {
                $this->assertRole($role);
                $trees['sessions/' . $role] = $this->copyTree($path, $directory . '/sessions/' . $role);
            }
            $trees['storage'] = $this->copyTree($persistentStorage, $directory . '/storage', ['cache', 'write-gate', 'object-storage', '.shared-jobs.lock']);
            $snapshot = ['version' => 1, 'started_at' => $started, 'completed_at' => microtime(true),
                'database_identity' => ReferenceAudit::databaseIdentity($db), 'driver' => $db->getAttribute(PDO::ATTR_DRIVER_NAME),
                'backend' => Manifest::backendIdentity($backend), 'application_backend' => $applicationBackend,
                'namespace' => $this->namespaceIdentity($backend),
                'configuration' => $configuration, 'pause' => $state, 'objects' => $objects, 'trees' => $trees,
                'reference_identity' => $reviewed->identity, 'sessions' => $sessions,
                'database_sha256' => hash_file('sha256', $database),
                'manifest_sha256' => hash_file('sha256', $directory . '/manifest'),
                'checkpoint_sha256' => hash_file('sha256', $directory . '/checkpoint')];
            $this->writePrivate($directory . '/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $this->syncTree($directory);
            $sessionDigests = [];
            foreach ($sessions as $role => $path) {
                $sessionDigests[$role] = hash('sha256', json_encode($trees['sessions/' . $role], JSON_THROW_ON_ERROR));
            }
            $state['backup'] = ['sha256' => hash_file('sha256', $directory . '/snapshot.json'),
                'manifest_sha256' => $snapshot['manifest_sha256'], 'checkpoint_sha256' => $snapshot['checkpoint_sha256'],
                'database_sha256' => $snapshot['database_sha256'], 'completed_at' => $snapshot['completed_at'],
                'sessions' => $sessionDigests];
            $this->gate->record($state);
            return ['sha256' => $state['backup']['sha256'],
                'started_at' => $started, 'completed_at' => $snapshot['completed_at'], 'objects' => count($objects)];
        });
    }

    public function restore(string $directory, string $reviewedDigest, PDO $db, Storage $backend,
        string $applicationBackend, array $sessions, string $persistentStorage, string $selectedDatabase): array
    {
        return $this->gate->exclusive(function (array $state) use ($directory, $reviewedDigest, $db, $backend,
            $applicationBackend, $sessions, $persistentStorage, $selectedDatabase) {
            Manifest::assertPrivatePath($directory . '/snapshot.json');
            if (!hash_equals($reviewedDigest, hash_file('sha256', $directory . '/snapshot.json'))) {
                throw new StorageException('Restore requires the reviewed snapshot SHA-256.');
            }
            $snapshot = json_decode(file_get_contents($directory . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
            $actual = ReferenceAudit::databaseIdentity($db);
            if (($snapshot['version'] ?? null) !== 1 || $actual !== $selectedDatabase
                || $actual === $snapshot['database_identity'] || $this->tables($db) !== []
                || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== $snapshot['driver']) {
                throw new StorageException('Restore requires an explicitly selected separate empty database of the same engine.');
            }
            if ($backend->listPage('uploads/', null, 1)['objects'] !== []
                || $this->namespaceIdentity($backend) === $snapshot['namespace']) {
                throw new StorageException('Restore requires a separate empty object namespace.');
            }
            $capturedRoles = array_values(array_map(
                static fn ($key) => substr($key, 9),
                array_filter(array_keys($snapshot['trees']), static fn ($key) => str_starts_with($key, 'sessions/'))
            ));
            $singleToSplit = $this->isSingleToSplit($capturedRoles, $sessions);
            if (!$singleToSplit && array_keys($sessions) !== $capturedRoles) {
                throw new StorageException('Restore must select every captured session role.');
            }
            foreach ($sessions as $path) {
                $this->assertFreshTree($path);
            }
            $this->assertFreshTree($persistentStorage, ['cache', 'write-gate']);
            foreach (['database.jsonl' => 'database_sha256', 'manifest' => 'manifest_sha256', 'checkpoint' => 'checkpoint_sha256'] as $name => $field) {
                $this->assertDigest($directory . '/' . $name, $snapshot[$field]);
            }
            foreach ($snapshot['objects'] as $object) {
                $this->assertRelative($object['file']);
                $this->assertDigest($directory . '/' . $object['file'], $object['sha256']);
            }
            foreach ($snapshot['trees'] as $prefix => $files) {
                foreach ($files as $relative => $metadata) {
                    $this->assertRelative($relative);
                    $this->assertDigest($directory . '/' . $prefix . '/' . $relative, $metadata['sha256']);
                }
            }
            $reviewed = $this->restoredReferences($directory, $snapshot, $sessions);
            // Durable target marker survives nontransactional DDL or interrupted transfers.
            $state['restore'] = ['snapshot' => $reviewedDigest, 'database' => $actual, 'state' => 'started', 'at' => microtime(true)];
            $this->gate->record($state);
            $this->restoreDatabase($db, $directory . '/database.jsonl');
            $this->assertLedgerOwner($db, $snapshot['application_backend']);
            $db->beginTransaction();
            try {
                $db->prepare('UPDATE file_operations SET backend = ? WHERE backend = ?')->execute([$applicationBackend, $snapshot['application_backend']]);
                $db->commit();
            } catch (\Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }
            foreach ($snapshot['objects'] as $key => $object) {
                $stream = fopen($directory . '/' . $object['file'], 'rb');
                try {
                    $backend->write($key, $stream, $object['mime'], $object['created_at']);
                } finally {
                    fclose($stream);
                }
                $stream = $backend->read($key);
                try {
                    $actualBytes = Inventory::fingerprint($stream);
                } finally {
                    fclose($stream);
                }
                $restoredMeta = $backend->stat($key);
                if ($actualBytes['sha256'] !== $object['sha256'] || $actualBytes['size'] !== $object['size']
                    || $restoredMeta === null || $restoredMeta['createdAt'] !== $object['created_at']
                    || $restoredMeta['mime'] !== $object['mime']) {
                    throw new StorageException('Restored object verification failed. Target remains paused.');
                }
            }
            if ($singleToSplit) {
                $restoredSessionTrees = LegacySessionTransition::restoreSingleToSplit(
                    $directory . '/sessions/app',
                    $sessions['public'],
                    $snapshot['trees']['sessions/app'],
                    $snapshot['configuration']['serializer']
                );
            } else {
                $restoredSessionTrees = [];
                foreach ($sessions as $role => $path) {
                    $this->assertRole($role);
                    $this->restoreTree($directory . '/sessions/' . $role, $path, $snapshot['trees']['sessions/' . $role]);
                    $restoredSessionTrees[$role] = $snapshot['trees']['sessions/' . $role];
                }
            }
            $this->restoreTree($directory . '/storage', $persistentStorage, $snapshot['trees']['storage']);
            $this->assertReferences($db, $backend, $sessions, $snapshot['configuration']['serializer'], $reviewed);
            foreach ($sessions as $path) {
                $this->syncTree($path);
            }
            $this->syncTree($persistentStorage);
            $state['restore']['state'] = 'verified';
            $state['restore']['completed_at'] = microtime(true);
            $state['restore']['application_backend'] = $applicationBackend;
            $state['restore']['database_sha256'] = $this->databaseFingerprint($db);
            $state['restore']['session_trees'] = $restoredSessionTrees;
            $this->gate->record($state);
            return $state['restore'];
        });
    }

    public function resumeRestored(string $directory, string $reviewedDigest, PDO $db, Storage $backend,
        array $sessions, array $targets): void
    {
        $this->gate->resume(function (array $state) use ($directory, $reviewedDigest, $db, $backend, $sessions, $targets) {
            Manifest::assertPrivatePath($directory . '/snapshot.json');
            $this->assertDigest($directory . '/snapshot.json', $reviewedDigest);
            $snapshot = json_decode(file_get_contents($directory . '/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
            $database = ReferenceAudit::databaseIdentity($db);
            if (($state['restore']['state'] ?? '') !== 'verified' || ($state['restore']['snapshot'] ?? '') !== $reviewedDigest
                || ($state['restore']['database'] ?? '') !== $database || $database === $snapshot['database_identity']) {
                throw new StorageException('Reopening requires a verified isolated restore of this exact snapshot.');
            }
            Cutover::targetIdentity($targets, $state, Manifest::backendIdentity($backend), $database,
                $snapshot['configuration']['serializer'], $this->gate, $sessions);
            $this->assertReferences($db, $backend, $sessions, $snapshot['configuration']['serializer'],
                $this->restoredReferences($directory, $snapshot, $sessions));
            $this->assertLedgerOwner($db, $state['restore']['application_backend']);
            if (($state['restore']['database_sha256'] ?? '') !== $this->databaseFingerprint($db)) {
                throw new StorageException('Restored database changed before reopening.');
            }
            $expectedSessionTrees = $state['restore']['session_trees'] ?? null;
            foreach ($sessions as $role => $path) {
                $expectedFiles = is_array($expectedSessionTrees) && array_key_exists($role, $expectedSessionTrees)
                    ? $expectedSessionTrees[$role]
                    : $snapshot['trees']['sessions/' . $role];
                foreach ($expectedFiles as $relative => $metadata) {
                    $this->assertRelative($relative);
                    if (is_link($path . '/' . $relative) || !is_file($path . '/' . $relative)
                        || hash_file('sha256', $path . '/' . $relative) !== $metadata['sha256']) {
                        throw new StorageException('Restored session state changed before reopening.');
                    }
                }
                if (is_array($expectedSessionTrees)) {
                    $actualFiles = [];
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                    foreach ($iterator as $file) {
                        if ($file->isLink() || !$file->isFile()) {
                            throw new StorageException('Restored session store contains an unsafe entry.');
                        }
                        $actualFiles[] = substr($file->getPathname(), strlen($path) + 1);
                    }
                    sort($actualFiles);
                    $expectedList = array_keys($expectedFiles);
                    sort($expectedList);
                    if ($actualFiles !== $expectedList) {
                        throw new StorageException('Restored session store file set changed before reopening.');
                    }
                }
            }
            foreach ($snapshot['objects'] as $key => $object) {
                $stream = $backend->read($key);
                try {
                    $fingerprint = Inventory::fingerprint($stream);
                } finally {
                    fclose($stream);
                }
                $restoredMeta = $backend->stat($key);
                if ($fingerprint['sha256'] !== $object['sha256'] || $fingerprint['size'] !== $object['size']
                    || $restoredMeta === null || $restoredMeta['createdAt'] !== $object['created_at']
                    || $restoredMeta['mime'] !== $object['mime']) {
                    throw new StorageException('Restored bytes changed before reopening.');
                }
            }
        });
    }

    private function restoredReferences(string $directory, array $snapshot, array $sessions): ?Manifest
    {
        // Version-one snapshots created before disposition support retain strict reference checks.
        if (!isset($snapshot['reference_identity'])) {
            return null;
        }
        $this->assertDigest($directory . '/manifest', $snapshot['manifest_sha256']);
        $this->assertDigest($directory . '/checkpoint', $snapshot['checkpoint_sha256']);
        $reviewed = Manifest::load($directory . '/manifest', $snapshot['reference_identity'], $directory . '/checkpoint');
        if ($reviewed->identity['database'] !== $snapshot['database_identity']
            || !in_array($snapshot['backend'], [$reviewed->identity['source'], $reviewed->identity['destination']], true)
            || $reviewed->identity['serializer'] !== $snapshot['configuration']['serializer']
            || $snapshot['sessions'] !== Cutover::sessionStores($reviewed->identity)) {
            throw new StorageException('Snapshot reference approvals differ from their original migration identity.');
        }
        $owners = [];
        $singleToSplit = $this->isSingleToSplit(array_keys($snapshot['sessions']), $sessions);
        foreach ($snapshot['sessions'] as $role => $path) {
            $targetRole = $singleToSplit && $role === 'app' ? 'public' : $role;
            foreach ($snapshot['trees']['sessions/' . $role] as $relative => $metadata) {
                $this->assertRelative($relative);
                $owners[hash('sha256', $path . '/' . $relative)] = hash('sha256', $sessions[$targetRole] . '/' . $relative);
            }
        }
        foreach ($reviewed->references as &$reference) {
            if ($reference['owner_type'] === 'session' && isset($owners[$reference['record']])) {
                $reference['record'] = $owners[$reference['record']];
            }
        }
        unset($reference);
        return $reviewed;
    }

    private function assertReferences(PDO $db, Storage $backend, array $sessions, string $serializer, ?Manifest $reviewed = null): void
    {
        if (array_keys($sessions) !== ['app'] && (array_keys($sessions) !== ['public', 'staff'] || $sessions['public'] === $sessions['staff']
            || str_starts_with($sessions['public'] . '/', $sessions['staff'] . '/')
            || str_starts_with($sessions['staff'] . '/', $sessions['public'] . '/'))) {
            throw new StorageException('Select the app session store or distinct public and staff session stores in order.');
        }
        foreach ($sessions as $path) {
            $identity = ['environment' => 'coordinated-recovery', 'database' => ReferenceAudit::databaseIdentity($db),
                'source' => Manifest::backendIdentity($backend), 'destination' => hash('sha256', 'separate backup namespace'),
                'sessions' => $path, 'serializer' => $serializer];
            $manifest = (new Inventory($backend, $identity, $backend instanceof LocalStorage ? $backend->migrationRoot() : null))->scan();
            $manifest->references = $reviewed?->references ?? [];
            if ((new ReferenceAudit($db, $backend, $identity, $path, $serializer))->audit($manifest) !== []) {
                throw new StorageException('Current database or staged session references cannot be recovered completely.');
            }
        }
    }

    private function assertLedgerOwner(PDO $db, string $backend): void
    {
        $query = $db->prepare('SELECT COUNT(*) FROM file_operations WHERE backend <> ?');
        $query->execute([$backend]);
        if ((int)$query->fetchColumn() !== 0) {
            throw new StorageException('Backup/restore found file intents owned by another backend.');
        }
    }

    private function namespaceIdentity(Storage $backend): string
    {
        if (!$backend instanceof LocalStorage) {
            return $backend->migrationNamespace();
        }
        $path = $backend->migrationRoot() . '/uploads';
        $stat = stat(is_dir($path) ? $path : $backend->migrationRoot());
        return hash('sha256', json_encode(['local', $backend->migrationRoot(), $stat['dev'], $stat['ino']], JSON_THROW_ON_ERROR));
    }

    private function tables(PDO $db): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            if ($db->query("SELECT name FROM sqlite_master WHERE type IN ('view','trigger') LIMIT 1")->fetchColumn() !== false) {
                throw new StorageException('Views and triggers require the operator database backup procedure.');
            }
            return $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        }
        $rows = $db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $row) {
            if ($row[1] !== 'BASE TABLE') {
                throw new StorageException('Views require the operator database backup procedure.');
            }
        }
        if ($db->query('SHOW TRIGGERS')->fetch() !== false) {
            throw new StorageException('Triggers require the operator database backup procedure.');
        }
        foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
            if ((int)$db->query('SELECT COUNT(*) FROM information_schema.' . $table . ' WHERE ' . $column . ' = DATABASE()')->fetchColumn() !== 0) {
                throw new StorageException('Stored routines and events require the operator database backup procedure.');
            }
        }
        $tables = array_column($rows, 0);
        sort($tables);
        return $tables;
    }

    private function dumpDatabase(PDO $db, string $path): void
    {
        $handle = fopen($path, 'xb');
        chmod($path, 0600);
        try {
            foreach ($this->tables($db) as $table) {
                $name = $this->identifier($table);
                if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                    $query = $db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
                    $query->execute([$table]);
                    $schema = $query->fetchColumn();
                } else {
                    $schema = $db->query('SHOW CREATE TABLE ' . $name)->fetch(PDO::FETCH_NUM)[1];
                }
                $this->jsonLine($handle, ['type' => 'schema', 'table' => $table, 'sql' => $schema]);
                $query = $db->query('SELECT * FROM ' . $name);
                while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    $this->jsonLine($handle, ['type' => 'row', 'table' => $table,
                        'values' => array_map(static fn ($value) => $value === null ? null : base64_encode((string)$value), $row)]);
                }
            }
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                foreach ($db->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL ORDER BY name") as $row) {
                    $this->jsonLine($handle, ['type' => 'index', 'sql' => $row['sql']]);
                }
            }
            if (!fflush($handle) || !fsync($handle)) {
                throw new StorageException('Unable to flush coordinated database snapshot.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function databaseFingerprint(PDO $db): string
    {
        $path = tempnam(sys_get_temp_dir(), 'amea-db-proof-');
        if ($path === false || !unlink($path)) {
            throw new StorageException('Unable to allocate restored database verification.');
        }
        try {
            $this->dumpDatabase($db, $path);
            return hash_file('sha256', $path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function treeIdentity(string $directory): string
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                throw new StorageException('Session verification encountered an unsafe file.');
            }
            $handle = fopen($file->getPathname(), 'rb');
            try {
                if (!flock($handle, LOCK_SH | LOCK_NB)) {
                    throw new StorageException('A session is busy during recovery verification.');
                }
                $fingerprint = Inventory::fingerprint($handle);
                $files[substr($file->getPathname(), strlen($directory) + 1)] = ['sha256' => $fingerprint['sha256'],
                    'size' => $fingerprint['size'], 'mtime' => $file->getMTime()];
            } finally {
                fclose($handle);
            }
        }
        ksort($files);
        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    private function syncTree(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $this->syncPath($file->getPathname());
        }
        $this->syncPath($directory);
        $this->syncPath(dirname($directory));
    }

    private function syncPath(string $path): void
    {
        $handle = fopen($path, 'r');
        try {
            if ($handle === false || !fsync($handle)) {
                throw new StorageException('Unable to persist coordinated recovery contents.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    private function restoreDatabase(PDO $db, string $path): void
    {
        $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $db->exec($mysql ? 'SET FOREIGN_KEY_CHECKS=0' : 'PRAGMA foreign_keys=OFF');
        $handle = fopen($path, 'rb');
        try {
            while (($line = fgets($handle)) !== false) {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (in_array($record['type'], ['schema', 'index'], true)) {
                    $db->exec($record['sql']);
                    continue;
                }
                if ($record['type'] !== 'row') {
                    throw new StorageException('Unknown database snapshot record.');
                }
                $columns = array_map($this->identifier(...), array_keys($record['values']));
                $values = array_map(static fn ($value) => $value === null ? null : base64_decode($value, true), $record['values']);
                $sql = 'INSERT INTO ' . $this->identifier($record['table']) . ' (' . implode(',', $columns) . ') VALUES ('
                    . implode(',', array_fill(0, count($values), '?')) . ')';
                $db->prepare($sql)->execute(array_values($values));
            }
        } finally {
            fclose($handle);
            $db->exec($mysql ? 'SET FOREIGN_KEY_CHECKS=1' : 'PRAGMA foreign_keys=ON');
        }
    }

    private function copyTree(string $source, string $destination, array $exclude = []): array
    {
        if (realpath($source) !== $source || !is_dir($source) || is_link($source)) {
            throw new StorageException('Backup trees must be canonical directories.');
        }
        if (!is_dir($destination)) {
            mkdir($destination, 0700, true);
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);
            if (in_array(explode('/', $relative)[0], $exclude, true)) {
                continue;
            }
            $this->assertRelative($relative);
            if (!$file->isFile() || $file->isLink()) {
                throw new StorageException('Backup trees contain a symlink or unsupported special file.');
            }
            $path = $destination . '/' . $relative;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            $stream = fopen($file->getPathname(), 'rb');
            try {
                if (!flock($stream, LOCK_SH | LOCK_NB)) {
                    throw new StorageException('A session or state file is still busy during backup.');
                }
                $files[$relative] = $this->copyStream($stream, $path) + ['mtime' => $file->getMTime()];
                if ($files[$relative]['size'] !== $file->getSize()) {
                    throw new StorageException('Persistent state changed during coordinated backup.');
                }
            } finally {
                fclose($stream);
            }
        }
        ksort($files);
        return $files;
    }

    private function assertFreshTree(string $directory, array $excluded = []): void
    {
        if (realpath($directory) !== $directory || !is_dir($directory) || is_link($directory)) {
            throw new StorageException('Restore requires canonical isolated state/session directories.');
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if (in_array(explode('/', $relative)[0], $excluded, true)) {
                continue;
            }
            if ($file->isLink() || (!$file->isDir() && $relative !== 'object-storage/.lock')) {
                throw new StorageException('Restore requires fresh empty session and persistent-state targets.');
            }
        }
    }

    private function restoreTree(string $source, string $destination, array $files): void
    {
        if (realpath($destination) !== $destination || is_link($destination)) {
            throw new StorageException('Restore trees require existing canonical isolated directories.');
        }
        foreach ($files as $relative => $metadata) {
            $path = $destination . '/' . $relative;
            if (file_exists($path) || is_link($path)) {
                throw new StorageException('Restore cannot overwrite existing session or persistent files.');
            }
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            if (!copy($source . '/' . $relative, $path) || !chmod($path, 0600) || !touch($path, $metadata['mtime'])) {
                throw new StorageException('Unable to restore the complete persistent state.');
            }
        }
    }

    private function copyStream(mixed $source, string $path): array
    {
        $output = fopen($path, 'xb');
        if ($output === false || !chmod($path, 0600)) {
            throw new StorageException('Unable to protect a backup object.');
        }
        try {
            if (stream_copy_to_stream($source, $output) === false || !fflush($output) || !fsync($output)) {
                throw new StorageException('Unable to capture complete backup bytes.');
            }
        } finally {
            fclose($output);
        }
        return ['sha256' => hash_file('sha256', $path), 'size' => filesize($path)];
    }

    private function writePrivate(string $path, string $bytes): void
    {
        $handle = fopen($path, 'xb');
        chmod($path, 0600);
        try {
            if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle) || !fsync($handle)) {
                throw new StorageException('Unable to persist snapshot evidence.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function jsonLine(mixed $handle, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        if (fwrite($handle, $line) !== strlen($line)) {
            throw new StorageException('Unable to write complete database snapshot.');
        }
    }

    private function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new StorageException('Unsupported database identifier in snapshot.');
        }
        return '`' . $name . '`';
    }

    private function assertRelative(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")
            || array_intersect(explode('/', $path), ['', '.', '..']) !== []) {
            throw new StorageException('Unsafe backup member path.');
        }
    }

    private function isSingleToSplit(array $capturedRoles, array $sessions): bool
    {
        return $capturedRoles === ['app'] && array_keys($sessions) === ['public', 'staff'];
    }

    private function assertRole(string $role): void
    {
        if (!in_array($role, ['app', 'public', 'staff'], true)) {
            throw new StorageException('Unknown session store role.');
        }
    }

    private function assertDigest(string $path, string $digest): void
    {
        Manifest::assertPrivatePath($path);
        if (!hash_equals($digest, hash_file('sha256', $path))) {
            throw new StorageException('Backup content integrity verification failed.');
        }
    }
}
