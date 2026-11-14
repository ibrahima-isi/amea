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

try {
    $arguments = array_slice($argv, 1);
    $operation = isset($arguments[0]) && !str_starts_with($arguments[0], '--') ? array_shift($arguments) : 'inventory';
    if (!in_array($operation, ['inventory', 'audit-references', 'copy', 'verify', 'reverse-copy'], true)) {
        throw new InvalidArgumentException('Use inventory, audit-references, copy, verify or reverse-copy.');
    }
    $allowed = ['environment', 'database', 'source', 'destination', 'manifest', 'review-manifest',
        'mappings', 'review-mappings', 'corrections', 'review-corrections', 'dispositions', 'review-dispositions', 'reference-backend'];
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
                    exit(3);
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
                exit(3);
            }
        }
        if ($operation === 'audit-references') {
            $differences = array_filter($manifest->references, static fn ($reference) => $reference['state'] !== 'resolved' && empty($reference['disposition']));
            fwrite(STDOUT, 'Reference differences ' . count($differences) . "\n");
            if ($differences !== []) {
                exit(3);
            }
        }
    }
    fwrite(STDOUT, 'Manifest SHA256 ' . $manifest->reviewIdentity() . "\nFiles " . count($manifest->files)
        . "\nReferences " . count($manifest->references) . "\nIssues " . count($manifest->issues) . "\n");
    exit(0);
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
