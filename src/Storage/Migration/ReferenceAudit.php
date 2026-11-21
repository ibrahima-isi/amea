<?php

namespace Amea\Storage\Migration;

use Amea\Repository\FileOperationRepository;
use Amea\Migration\MigrationRuntime;
use Amea\Storage\LocalStorage;
use Amea\Storage\Storage;
use Amea\Storage\StorageException;
use Amea\Storage\ValidatesStorageKeys;
use PDO;

final class ReferenceAudit
{
    use ValidatesStorageKeys;

    private const COLUMNS = [
        'students' => ['id', ['identity_document', 'cv_path']],
        'slider_images' => ['id', ['image_path']],
        'journal_posts' => ['id', ['cover_path']],
        'file_operations' => ['operation_id', ['keys_json', 'expected_identity', 'expected_cv']],
    ];

    public function __construct(private PDO $db, private Storage $source, private array $identity, private string $sessionDirectory, private string $serializer, private string $backendRole = 'source')
    {
        if (self::databaseIdentity($db) !== ($identity['database'] ?? null)
            || !in_array($backendRole, ['source', 'destination'], true) || Manifest::backendIdentity($source) !== ($identity[$backendRole] ?? null)
            || realpath($sessionDirectory) !== $sessionDirectory || !is_dir($sessionDirectory)
            || $sessionDirectory !== ($identity['sessions'] ?? null) || $serializer !== ($identity['serializer'] ?? null)
            || !in_array($serializer, ['php', 'php_serialize', 'php_binary'], true)) {
            throw new StorageException('Reference audit target or session configuration mismatch.');
        }
    }

    public function audit(Manifest $manifest): array
    {
        $manifest->assertIdentity($this->identity);
        $references = [];
        foreach (self::COLUMNS as $table => [$primaryKey, $columns]) {
            $rows = $this->db->query('SELECT ' . $primaryKey . ', ' . implode(', ', $columns) . ' FROM ' . $table . ' ORDER BY ' . $primaryKey);
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                foreach ($columns as $column) {
                    if ($column === 'keys_json') {
                        $keys = json_decode($row[$column], true, 512, JSON_THROW_ON_ERROR);
                        if (!is_array($keys) || !array_is_list($keys)) {
                            throw new StorageException('Operation ledger keys are invalid.');
                        }
                        foreach ($keys as $index => $key) {
                            $references[] = $this->reference($manifest, 'database', $table, (string) $row[$primaryKey], $column, $key, $index, $row[$column]);
                        }
                    } elseif ($row[$column] !== null && $row[$column] !== '') {
                        $references[] = $this->reference($manifest, 'database', $table, (string) $row[$primaryKey], $column, $row[$column]);
                    }
                }
            }
        }
        $this->sessionReferences($manifest, $references, $this->sessionDirectory);
        foreach ($references as &$reference) {
            foreach ($manifest->references as $previous) {
                if (!empty($previous['disposition']) && $reference['state'] === 'missing'
                    && $reference['owner_type'] === $previous['owner_type'] && $reference['table'] === $previous['table']
                    && $reference['record'] === $previous['record'] && $reference['column'] === $previous['column']
                    && $reference['index'] === $previous['index'] && $reference['original_value'] === $previous['original_value']) {
                    $reference['disposition'] = $previous['disposition'];
                }
            }
        }
        unset($reference);
        $manifest->references = $references;
        return array_values(array_filter($references, static fn ($reference) => $reference['state'] !== 'resolved' && empty($reference['disposition'])));
    }

    /** Identity includes the server actually reached, independently of CLI selection. */
    public static function databaseIdentity(PDO $db): string
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $server = $db->query('SELECT @@hostname AS host, @@port AS port, DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
            return hash('sha256', json_encode(['mysql', $server, $db->getAttribute(PDO::ATTR_CONNECTION_STATUS)], JSON_THROW_ON_ERROR));
        }
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return hash('sha256', json_encode(['sqlite', $db->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC)], JSON_THROW_ON_ERROR));
        }
        throw new StorageException('Unsupported migration database driver.');
    }

    /** Reviewed whole-column CAS operations, sharing Doctrine's existing advisory lock. */
    public function correct(Manifest $manifest, array $mappings, string $reviewedDigest, Storage $destination, MigrationRuntime $runtime): void
    {
        $manifest->assertIdentity($this->identity);
        if ($mappings === [] || $reviewedDigest !== hash('sha256', json_encode($mappings, JSON_THROW_ON_ERROR))
            || Manifest::backendIdentity($destination) !== $this->identity['destination']
            || self::databaseIdentity($this->db) !== $this->identity['database']
            || $runtime->connection()->getNativeConnection() !== $this->db || $this->db->inTransaction()) {
            throw new StorageException('Correction review, database, destination or lock connection identity mismatch.');
        }
        $manifest->locked(function (Manifest $manifest) use ($mappings, $reviewedDigest, $destination, $runtime) {
            $result = $runtime->withLock(function () use ($manifest, $mappings, $reviewedDigest, $destination, $runtime) {
                try {
                    $runtime->assertNoInterruptedSchemaMigration();
                    $intent = null;
                    foreach ($manifest->corrections as $index => $correction) {
                        if ($correction['sha256'] === $reviewedDigest) {
                            $intent = $index;
                        }
                    }
                    $recovering = $intent !== null;
                    $seen = [];
                    foreach ($mappings as $mapping) {
                        $table = $mapping['table'] ?? '';
                        $column = $mapping['column'] ?? '';
                        if (!isset(self::COLUMNS[$table]) || !in_array($column, self::COLUMNS[$table][1], true)
                            || !is_string($mapping['record'] ?? null) || $mapping['record'] === ''
                            || !is_string($mapping['expected_old'] ?? null) || !is_string($mapping['new_value'] ?? null)
                            || $mapping['expected_old'] === $mapping['new_value']) {
                            throw new StorageException('Invalid reviewed correction. Only known file-reference columns can change.');
                        }
                        $identity = json_encode([$table, $mapping['record'], $column], JSON_THROW_ON_ERROR);
                        if (isset($seen[$identity])) {
                            throw new StorageException('Duplicate or ambiguous correction mapping.');
                        }
                        $seen[$identity] = true;
                        $known = $recovering && $manifest->corrections[$intent]['mappings'] === $mappings;
                        foreach ($manifest->references as $reference) {
                            $old = $column === 'keys_json' ? $reference['container_value'] : $reference['original_value'];
                            if ($reference['owner_type'] === 'database' && $reference['table'] === $table
                                && $reference['record'] === $mapping['record'] && $reference['column'] === $column && $old === $mapping['expected_old']) {
                                $known = true;
                            }
                        }
                        if (!$known) {
                            throw new StorageException('Expected-old correction was not present in the reviewed reference inventory.');
                        }
                        $keys = $column === 'keys_json' ? json_decode($mapping['new_value'], true, 512, JSON_THROW_ON_ERROR) : [$mapping['new_value']];
                        if (!is_array($keys) || !array_is_list($keys) || $keys === []) {
                            throw new StorageException('Corrected ledger keys must be a nonempty list.');
                        }
                        foreach ($keys as $key) {
                            $this->verifyCorrectionKey($manifest, $destination, $key);
                        }
                    }
                    if ($intent === null) {
                        $intent = count($manifest->corrections);
                        $manifest->corrections[] = ['sha256' => $reviewedDigest, 'mappings' => $mappings, 'state' => 'prepared', 'at' => time()];
                        $manifest->checkpoint();
                    }
                    $this->db->beginTransaction();
                    try {
                        (new FileOperationRepository($this->db))->lockMembers();
                        $states = [];
                        foreach ($mappings as $mapping) {
                            $table = $mapping['table'];
                            $column = $mapping['column'];
                            $primaryKey = self::COLUMNS[$table][0];
                            $query = $this->db->prepare("SELECT $column FROM $table WHERE $primaryKey = ? FOR UPDATE");
                            $query->execute([$mapping['record']]);
                            $rows = $query->fetchAll(PDO::FETCH_COLUMN);
                            if (count($rows) !== 1) {
                                throw new StorageException('Correction owner is absent or ambiguous.');
                            }
                            $value = $rows[0];
                            if ($value === $mapping['expected_old']) {
                                $states[] = 'old';
                            } elseif ($recovering && $value === $mapping['new_value']) {
                                $states[] = 'new';
                            } else {
                                throw new StorageException('Correction expected-old value changed. No corrections committed.');
                            }
                        }
                        if (count(array_unique($states)) !== 1) {
                            throw new StorageException('Mixed correction state requires operator reconciliation.');
                        }
                        if ($states[0] === 'old') {
                            foreach ($mappings as $mapping) {
                                $table = $mapping['table'];
                                $column = $mapping['column'];
                                $primaryKey = self::COLUMNS[$table][0];
                                $this->db->prepare("UPDATE $table SET $column = ? WHERE $primaryKey = ?")->execute([$mapping['new_value'], $mapping['record']]);
                            }
                        }
                        $this->db->commit();
                    } catch (\Throwable $error) {
                        if ($this->db->inTransaction()) {
                            $this->db->rollBack();
                        }
                        throw $error;
                    }
                    $manifest->corrections[$intent]['state'] = 'applied';
                    $manifest->checkpoint();
                } catch (\Throwable $error) {
                    // Return failures so the runtime explicitly releases its lock while PDO is retained.
                    return $error;
                }
            });
            if ($result instanceof \Throwable) {
                throw $result;
            }
        });
    }

    private function verifyCorrectionKey(Manifest $manifest, Storage $destination, mixed $key): void
    {
        if (!is_string($key)) {
            throw new StorageException('Corrected reference is invalid.');
        }
        $this->validateKey($key);
        $files = array_values(array_filter($manifest->files, static fn ($file) => $file['destination_key'] === $key && ($file['source_state'] ?? 'present') === 'present'));
        if (count($files) !== 1 || $files[0]['verify_state'] !== 'verified') {
            throw new StorageException('Corrected reference has no unique verified destination mapping.');
        }
        $stream = $destination->read($key);
        try {
            $fingerprint = Inventory::fingerprint($stream);
        } finally {
            fclose($stream);
        }
        if ($fingerprint['size'] !== $files[0]['size'] || $fingerprint['sha256'] !== $files[0]['sha256']) {
            throw new StorageException('Corrected reference destination bytes differ.');
        }
    }

    private function reference(Manifest $manifest, string $type, string $table, string $record, string $column, mixed $value, ?int $index = null, ?string $container = null): array
    {
        if (!is_string($value) || $value === '') {
            throw new StorageException('A file reference has an invalid type.');
        }
        $reference = ['owner_type' => $type, 'table' => $table, 'record' => $record, 'column' => $column,
            'original_value' => $value, 'resolved_key' => null, 'state' => 'missing', 'correction_state' => 'pending',
            'index' => $index, 'container_value' => $container, 'candidates' => []];
        $files = $manifest->files;
        if ($this->backendRole === 'destination') {
            $files = array_column($files, null, 'destination_key');
        }
        if (isset($files[$value]) && ($this->backendRole === 'destination' || ($files[$value]['source_state'] ?? 'present') !== 'removed') && $files[$value]['valid_key']) {
            if ($this->objectPresent($value)) {
                $reference['resolved_key'] = $value;
                $reference['state'] = 'resolved';
                $reference['correction_state'] = 'unnecessary';
            }
            return $reference;
        }
        $normalized = preg_replace('~^(?:\./)+~', '', ltrim($value, '/'));
        foreach ($files as $key => $file) {
            if (($file['source_state'] ?? 'present') === 'removed' || !$file['valid_key']) {
                continue;
            }
            if ($normalized === $key || (!str_contains($normalized, '/') && basename($key) === $normalized)) {
                if ($this->objectPresent($key)) {
                    $reference['candidates'][] = $key;
                }
            }
        }
        if (count($reference['candidates']) === 1) {
            $reference['state'] = 'correction-required';
            $reference['resolved_key'] = $reference['candidates'][0];
        } elseif (count($reference['candidates']) > 1) {
            $reference['state'] = 'ambiguous';
        } else {
            try {
                $this->validateKey($value);
            } catch (StorageException) {
                $reference['state'] = 'invalid';
                // A legacy basename with no possible object is still confirmed missing.
                if (!str_contains($value, '/') && !str_contains($value, '\\')) {
                    $reference['state'] = 'missing';
                }
                return $reference;
            }
            foreach ($manifest->issues as $issue) {
                if (in_array($issue['type'], ['symlink', 'nonregular', 'invalid-key'], true)
                    && ($value === $issue['key'] || str_starts_with($value, $issue['key'] . '/'))) {
                    $reference['state'] = 'unsafe';
                    return $reference;
                }
            }
            // LocalStorage stat creates its lock directory. Raw presence preserves read-only inventory.
            if ($this->objectPresent($value)) {
                $reference['state'] = 'not-inventoried';
            }
        }
        return $reference;
    }

    private function objectPresent(string $key): bool
    {
        return $this->source instanceof LocalStorage ? $this->localPresence($key) : $this->source->stat($key) !== null;
    }

    private function localPresence(string $key): bool
    {
        $path = $this->source->migrationRoot();
        foreach (explode('/', $key) as $segment) {
            if (!is_dir($path) || !is_readable($path) || !is_executable($path)) {
                throw new StorageException('A referenced source parent is inaccessible.');
            }
            $path .= '/' . $segment;
            clearstatcache(true, $path);
            if (is_link($path)) {
                throw new StorageException('A referenced source path contains a symlink.');
            }
            if (@lstat($path) === false) {
                return false;
            }
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new StorageException('A referenced source is inaccessible or nonregular.');
        }
        return true;
    }

    private function sessionReferences(Manifest $manifest, array &$references, string $directory): void
    {
        $names = @scandir($directory);
        if ($names === false) {
            throw new StorageException('Unable to enumerate the private session store.');
        }
        foreach (array_diff($names, ['.', '..']) as $name) {
            $path = $directory . '/' . $name;
            if (is_link($path) || (!is_dir($path) && !is_file($path))) {
                throw new StorageException('Private session store contains an unsafe entry.');
            }
            if (is_dir($path)) {
                $this->sessionReferences($manifest, $references, $path);
                continue;
            }
            if (!str_starts_with($name, 'sess_')) {
                continue;
            }
            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                throw new StorageException('Unable to read a private session record.');
            }
            try {
                if (!flock($handle, LOCK_SH | LOCK_NB)) {
                    throw new StorageException('A private session is busy. Retry its audit after draining writers.');
                }
                $bytes = stream_get_contents($handle, 16 * 1024 * 1024 + 1);
                if ($bytes === false || strlen($bytes) > 16 * 1024 * 1024 || !feof($handle)) {
                    throw new StorageException('Private session record exceeds the safe audit limit.');
                }
                $data = $this->decodeSession($bytes);
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
            $pending = $data['pending_registration']['db_data'] ?? [];
            if (!is_array($pending)) {
                throw new StorageException('Private staged registration has an invalid structure.');
            }
            foreach (['identity_document', 'cv_path'] as $column) {
                if (isset($pending[$column]) && $pending[$column] !== '') {
                    $references[] = $this->reference($manifest, 'session', 'pending_registration.db_data',
                        hash('sha256', $path), $column, $pending[$column]);
                }
            }
        }
    }

    /** Decode supported PHP session formats without sessions, classes or object hooks. */
    private function decodeSession(string $bytes): array
    {
        $position = 0;
        if ($this->serializer === 'php_serialize') {
            $result = $this->serializedValue($bytes, $position);
            if (!is_array($result) || $position !== strlen($bytes)) {
                throw new StorageException('Invalid private session serialization.');
            }
            return $result;
        }
        $result = [];
        while ($position < strlen($bytes)) {
            if ($this->serializer === 'php_binary') {
                $length = ord($bytes[$position++]);
                if ($length > 127 || $length === 0 || $position + $length > strlen($bytes)) {
                    throw new StorageException('Invalid binary session field.');
                }
                $key = substr($bytes, $position, $length);
                $position += $length;
            } else {
                $separator = strpos($bytes, '|', $position);
                if ($separator === false || $separator === $position) {
                    throw new StorageException('Invalid private session field.');
                }
                $key = substr($bytes, $position, $separator - $position);
                $position = $separator + 1;
            }
            if (array_key_exists($key, $result)) {
                throw new StorageException('Duplicate private session field.');
            }
            $result[$key] = $this->serializedValue($bytes, $position);
        }
        return $result;
    }

    private function serializedValue(string $bytes, int &$position, int $depth = 0): mixed
    {
        if ($depth > 64 || $position >= strlen($bytes)) {
            throw new StorageException('Invalid or excessively nested private session value.');
        }
        if (substr($bytes, $position, 2) === 'N;') {
            $position += 2;
            return null;
        }
        if (preg_match('/\G([bid]):([^;]*);/', $bytes, $match, 0, $position)) {
            $position += strlen($match[0]);
            return match ($match[1]) {
                'b' => match ($match[2]) { '0' => false, '1' => true, default => throw new StorageException('Invalid serialized boolean.') },
                'i' => filter_var($match[2], FILTER_VALIDATE_INT) !== false ? (int) $match[2] : throw new StorageException('Invalid serialized integer.'),
                'd' => is_numeric($match[2]) ? (float) $match[2] : throw new StorageException('Invalid serialized number.'),
            };
        }
        if (preg_match('/\Gs:([0-9]+):"/', $bytes, $match, 0, $position)) {
            $length = (int) $match[1];
            $position += strlen($match[0]);
            if ($length < 0 || $length > strlen($bytes) - $position || substr($bytes, $position + $length, 2) !== '";') {
                throw new StorageException('Invalid serialized string length.');
            }
            $value = substr($bytes, $position, $length);
            $position += $length + 2;
            return $value;
        }
        if (preg_match('/\Ga:([0-9]+):\{/', $bytes, $match, 0, $position)) {
            $count = (int) $match[1];
            if ($count < 0 || $count > 100000) {
                throw new StorageException('Private session array exceeds the safe audit limit.');
            }
            $position += strlen($match[0]);
            $value = [];
            for ($index = 0; $index < $count; $index++) {
                $key = $this->serializedValue($bytes, $position, $depth + 1);
                if ((!is_string($key) && !is_int($key)) || array_key_exists($key, $value)) {
                    throw new StorageException('Invalid or duplicate serialized array key.');
                }
                $value[$key] = $this->serializedValue($bytes, $position, $depth + 1);
            }
            if (substr($bytes, $position, 1) !== '}') {
                throw new StorageException('Invalid serialized array boundary.');
            }
            $position++;
            return $value;
        }
        throw new StorageException('Unsupported private session value. Objects and serialization references require explicit operator review.');
    }
}
