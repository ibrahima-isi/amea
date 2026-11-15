<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\Copier;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\Storage;
use Amea\Storage\StorageException;
use Amea\Storage\StorageFactory;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

/** Explicit migration configuration never loads the web application's .env. */
function migrationEnvironment(string $name): string
{
    return (string) (getenv($name) !== false ? getenv($name) : '');
}

function migrationBackend(string $role): Storage
{
    $prefix = 'MIGRATION_' . strtoupper($role) . '_';
    $driver = migrationEnvironment($prefix . 'DRIVER');
    if (!in_array($driver, ['local', 's3'], true)) {
        throw new InvalidArgumentException('Select both migration backend drivers explicitly.');
    }
    $root = migrationEnvironment($prefix . 'ROOT');
    if ($driver === 'local' && ($root === '' || !str_starts_with($root, '/') || realpath($root) !== $root)) {
        throw new InvalidArgumentException('Local backends require explicit canonical absolute roots.');
    }
    $environment = $_ENV;
    try {
        $_ENV['STORAGE_DRIVER'] = $driver;
        foreach (['S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY', 'S3_PATH_STYLE'] as $name) {
            $_ENV[$name] = migrationEnvironment($prefix . $name);
        }
        if ($_ENV['S3_PATH_STYLE'] === '') {
            $_ENV['S3_PATH_STYLE'] = 'false';
        }
        return StorageFactory::fromEnvironment($root === '' ? dirname(__DIR__) : $root);
    } finally {
        $_ENV = $environment;
    }
}

/** Raw file digest is reviewed before parsing any operator-authored mapping. */
function reviewedMigrationMappings(array $options, string $name): array
{
    if (!isset($options[$name])) {
        return [];
    }
    $path = $options[$name];
    Manifest::assertPrivatePath($path);
    $bytes = @file_get_contents($path);
    if ($bytes === false || ($options['review-' . $name] ?? '') !== hash('sha256', $bytes)) {
        throw new InvalidArgumentException('Mapping files require their reviewed SHA-256 identity.');
    }
    $mappings = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($mappings) || !array_is_list($mappings) || $mappings === []) {
        throw new InvalidArgumentException('Reviewed mappings must be a nonempty JSON array.');
    }
    if ($name === 'mappings') {
        $seen = [];
        foreach ($mappings as $mapping) {
            if (!is_string($mapping['source_key'] ?? null) || isset($seen[$mapping['source_key']])) {
                throw new InvalidArgumentException('Duplicate or invalid source mapping.');
            }
            $seen[$mapping['source_key']] = true;
        }
    }
    return $mappings;
}

function storageMigrationMain(array $argv): int
{
    $arguments = array_slice($argv, 1);
    $operation = isset($arguments[0]) && !str_starts_with($arguments[0], '--') ? array_shift($arguments) : 'inventory';
    if (!in_array($operation, ['inventory', 'audit-references', 'copy', 'verify', 'reverse-copy', 'pause', 'final-check', 'resume', 'status', 'drain-intents'], true)) {
        throw new InvalidArgumentException('Select a documented inventory, transfer or write-gate command.');
    }
    $allowed = ['environment', 'database', 'source', 'destination', 'manifest', 'review-manifest',
        'mappings', 'review-mappings', 'corrections', 'review-corrections', 'dispositions', 'review-dispositions', 'reference-backend', 'targets'];
    $options = [];
    foreach ($arguments as $argument) {
        if (!preg_match('/^--([a-z-]+)=(.+)$/s', $argument, $match) || !in_array($match[1], $allowed, true) || isset($options[$match[1]])) {
            throw new InvalidArgumentException('Use unique --name=value options.');
        }
        $options[$match[1]] = $match[2];
    }
    foreach (['environment', 'database', 'source', 'destination', 'manifest'] as $name) {
        if (empty($options[$name])) {
            throw new InvalidArgumentException('Every command requires explicit environment, database, source, destination and manifest selections.');
        }
    }
    if ($options['environment'] !== migrationEnvironment('MIGRATION_ENVIRONMENT')) {
        throw new InvalidArgumentException('The selected environment identity differs from its configuration.');
    }
    Manifest::assertPrivatePath($options['manifest']);
    $source = migrationBackend('source');
    $destination = migrationBackend('destination');
    Manifest::assertBackendPair($source, $destination);
    foreach ([$source, $destination] as $backend) {
        if (!$backend instanceof LocalStorage) {
            continue;
        }
        foreach (['manifest', 'mappings', 'corrections', 'dispositions'] as $name) {
            if (isset($options[$name]) && str_starts_with($options[$name], $backend->migrationRoot() . '/')) {
                throw new InvalidArgumentException('Private evidence must be outside both selected local project roots.');
            }
        }
    }
    $runtime = null;
    $dsn = migrationEnvironment('MIGRATION_DB_DSN');
    if ($dsn !== '') {
        $file = substr($dsn, 7);
        if (!str_starts_with($dsn, 'sqlite:/') || realpath($file) !== $file || !is_file($file)) {
            throw new InvalidArgumentException('Fixture SQLite selection requires an existing canonical absolute database file.');
        }
        $db = new PDO($dsn, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } else {
        $runtime = MigrationRuntime::fromEnvironment();
        $db = $runtime->connection()->getNativeConnection();
    }
    $sessionPath = migrationEnvironment('MIGRATION_SESSION_PATH');
    $serializer = migrationEnvironment('MIGRATION_SESSION_SERIALIZER');
    $identity = ['environment' => $options['environment'], 'database' => ReferenceAudit::databaseIdentity($db),
        'source' => Manifest::backendIdentity($source), 'destination' => Manifest::backendIdentity($destination),
        'sessions' => $sessionPath, 'serializer' => $serializer];
    foreach (['database', 'source', 'destination'] as $name) {
        if (!hash_equals($identity[$name], $options[$name])) {
            throw new InvalidArgumentException('The selected target identity differs from the actual configuration.');
        }
    }
    $gate = \Amea\Core\FileWriteGate::application();
    $cutover = new \Amea\Storage\Migration\Cutover($gate, $db, $source, $destination, $identity, $options['manifest']);
    if ($operation === 'pause') {
        fwrite(STDOUT, json_encode($cutover->pause(), JSON_THROW_ON_ERROR) . "\n");
        return 0;
    }
    if ($operation === 'status') {
        fwrite(STDOUT, json_encode($gate->status(), JSON_THROW_ON_ERROR) . "\n");
        return 0;
    }
    if (in_array($operation, ['final-check', 'resume'], true)) {
        if (!isset($options['targets'])) {
            throw new InvalidArgumentException('Collect all actual target reports in a private --targets JSON array.');
        }
        Manifest::assertPrivatePath($options['targets']);
        $targets = json_decode(file_get_contents($options['targets']), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($targets) || !array_is_list($targets)) {
            throw new InvalidArgumentException('Target reports must be a JSON array.');
        }
        $result = $operation === 'final-check' ? $cutover->finalCheck($targets) : $cutover->resume($targets);
        fwrite(STDOUT, json_encode(['operation' => $operation, 'verification' => $result], JSON_THROW_ON_ERROR) . "\n");
        return 0;
    }
    if ($operation === 'drain-intents') {
        $gate->exclusive(function (array $state) use ($identity, $db, $source) {
            if (($state['selection'] ?? null) !== $identity) {
                throw new StorageException('Paused migration selection mismatch.');
            }
            $environment = $_ENV;
            try {
                $_ENV['STORAGE_DRIVER'] = migrationEnvironment('MIGRATION_SOURCE_DRIVER');
                $_ENV['S3_ENDPOINT'] = migrationEnvironment('MIGRATION_SOURCE_S3_ENDPOINT');
                $_ENV['S3_BUCKET'] = migrationEnvironment('MIGRATION_SOURCE_S3_BUCKET');
                $backend = StorageFactory::identity($source instanceof LocalStorage ? $source->migrationRoot() : dirname(__DIR__));
            } finally {
                $_ENV = $environment;
            }
            $lifecycle = new \Amea\Service\FileLifecycleService($db, $source, $backend);
            $lifecycle->retryDeletions(100000, migrationEnvironment('AMEA_TRASH_BACKUP_CONFIRMED') === '1');
            if ((int)$db->query('SELECT COUNT(*) FROM file_operations')->fetchColumn() !== 0) {
                throw new StorageException('Pending intents remain. Resolve them on their owning backend before cutover.');
            }
        });
        return 0;
    }
    $run = function () use ($operation, $options, $source, $destination, $runtime, $db, $sessionPath, $serializer, $identity): int {
        $referenceBackend = $options['reference-backend'] ?? 'source';
        $auditor = new ReferenceAudit($db, $referenceBackend === 'destination' ? $destination : $source,
            $identity, $sessionPath, $serializer, $referenceBackend);
        $inventory = new Inventory($source, $identity, $source instanceof LocalStorage ? $source->migrationRoot() : null);
        $mappings = reviewedMigrationMappings($options, 'mappings');
        $corrections = reviewedMigrationMappings($options, 'corrections');
        $dispositions = reviewedMigrationMappings($options, 'dispositions');
        if (($mappings !== [] && !in_array($operation, ['inventory', 'copy', 'reverse-copy'], true))
            || ($corrections !== [] && $operation !== 'audit-references') || ($dispositions !== [] && $operation === 'inventory')) {
            throw new InvalidArgumentException('Mappings belong to inventory/copy/reverse-copy and corrections belong to audit-references.');
        }
        if ($operation === 'inventory') {
            if ($referenceBackend !== 'source') {
                throw new InvalidArgumentException('Initial inventory audits the selected source backend.');
            }
            $manifest = $inventory->scan();
            foreach ($mappings as $mapping) {
                $manifest->map($mapping['source_key'] ?? '', $mapping['destination_key'] ?? '', $mapping['expected_sha256'] ?? '');
            }
            $auditor->audit($manifest);
            $manifest->save($options['manifest']);
        } else {
            $manifest = Manifest::load($options['manifest'], $identity);
            $mutating = in_array($operation, ['copy', 'reverse-copy'], true) || $corrections !== [] || $dispositions !== [];
            if ($mutating && ($options['review-manifest'] ?? '') !== $manifest->reviewIdentity()) {
                throw new InvalidArgumentException('Mutation requires --review-manifest with the immutable inventory SHA-256.');
            }
            if ($operation === 'reverse-copy' && (!($destination instanceof LocalStorage) || $referenceBackend !== 'source')) {
                throw new InvalidArgumentException('Reverse-copy selects the current active source, current references and a local recovery destination.');
            }
            if ($corrections !== [] && $runtime === null) {
                throw new InvalidArgumentException('Reference correction requires the existing MariaDB migration runtime and advisory lock.');
            }
            $manifest->locked(function (Manifest $manifest) use ($auditor, $dispositions) {
                $auditor->audit($manifest);
                if ($dispositions !== []) {
                    $manifest->dispose($dispositions, hash('sha256', json_encode($dispositions, JSON_THROW_ON_ERROR)));
                }
                $manifest->checkpoint();
            });
            if ($corrections !== []) {
                $auditor->correct($manifest, $corrections, hash('sha256', json_encode($corrections, JSON_THROW_ON_ERROR)), $destination, $runtime);
                $manifest->locked(function (Manifest $manifest) use ($auditor) {
                    $auditor->audit($manifest);
                    $manifest->checkpoint();
                });
            }
            $copier = new Copier($source, $destination, $identity, $inventory);
            if (in_array($operation, ['copy', 'reverse-copy'], true)) {
                $copier->copy($manifest, $mappings, $mappings === [] ? null : hash('sha256', json_encode($mappings, JSON_THROW_ON_ERROR)));
                // New submissions and staged sessions are read again after transfer.
                $manifest->locked(function (Manifest $manifest) use ($auditor) {
                    $auditor->audit($manifest);
                    $manifest->checkpoint();
                });
                if ($operation === 'reverse-copy') {
                    $differences = array_filter($manifest->references, static fn ($reference) => $reference['state'] !== 'resolved' && empty($reference['disposition']));
                    if ($differences !== []) {
                        fwrite(STDOUT, 'Reverse-copy reference differences ' . count($differences) . "\n");
                        return 3;
                    }
                }
            }
            if ($operation === 'verify') {
                $destinationAudit = new ReferenceAudit($db, $destination, $identity, $sessionPath, $serializer, 'destination');
                $manifest->locked(function (Manifest $manifest) use ($destinationAudit) {
                    $destinationAudit->audit($manifest);
                    $manifest->checkpoint();
                });
                $differences = $copier->verify($manifest);
                fwrite(STDOUT, 'Verification differences ' . count($differences) . "\n");
                if ($differences !== []) {
                    return 3;
                }
            }
            if ($operation === 'audit-references') {
                $differences = array_filter($manifest->references, static fn ($reference) => $reference['state'] !== 'resolved' && empty($reference['disposition']));
                fwrite(STDOUT, 'Reference differences ' . count($differences) . "\n");
                if ($differences !== []) {
                    return 3;
                }
            }
        }
        fwrite(STDOUT, 'Manifest SHA256 ' . $manifest->reviewIdentity() . "\nFiles " . count($manifest->files)
            . "\nReferences " . count($manifest->references) . "\nIssues " . count($manifest->issues) . "\n");
        return 0;
    };
    $paused = $gate->status()['paused'];
    if ($paused || isset($options['corrections']) || isset($options['dispositions']) || $operation === 'reverse-copy') {
        return $gate->exclusive(function (array $state) use ($identity, $run) {
            if (($state['selection'] ?? null) !== $identity) {
                throw new StorageException('Paused migration selection mismatch.');
            }
            return $run();
        });
    }
    return $gate->mutate($run);
}

try {
    exit(storageMigrationMain($argv));
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
} catch (StorageException $error) {
    fwrite(STDERR, 'Storage migration failed: ' . $error->getMessage() . "\n");
    exit(1);
} catch (Throwable $error) {
    // Do not print session bytes, SQL parameter values, credentials or provider responses.
    fwrite(STDERR, "Storage migration failed while reading the selected configuration, database or private evidence.\n");
    exit(1);
}
