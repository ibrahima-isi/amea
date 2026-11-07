<?php

declare(strict_types=1);

namespace Amea\Migration;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class BaselineEnrollment
{
    private const SQL_BASELINE_SHA256 = '6b90bcf1c63c4751f64d8458624348b8aa2f8fd29723a099305563185bbead0d';

    public function __construct(private readonly MigrationRuntime $runtime)
    {
    }

    public function enroll(string $database, string $evidencePath): bool
    {
        $connection = $this->runtime->connection();
        if ($database !== $connection->fetchOne('SELECT DATABASE()')) {
            throw new RuntimeException('The connected database does not match the explicit enrollment target.');
        }
        $this->verifyEvidence($connection, $database, $evidencePath);
        $this->verifySchema($connection);
        $this->verifyData($connection);

        $recorded = $this->runtime->recordedVersions();
        if (array_diff($recorded, MigrationRuntime::BASELINE_VERSIONS) !== []) {
            throw new RuntimeException('Unknown recorded versions. Review the migration ledger before enrollment.');
        }
        if ($recorded !== [] && array_diff(MigrationRuntime::BASELINE_VERSIONS, $recorded) !== []) {
            throw new RuntimeException('Partial migration baseline. Reconcile the schema and ledger before enrollment.');
        }
        if ($recorded !== []) {
            $this->assertTransactionalLedger($connection);
            return false;
        }

        // Provisioning is DDL and must finish before the atomic enrollment transaction.
        $this->runtime->doctrine->getMetadataStorage()->ensureInitialized();
        $this->assertTransactionalLedger($connection);
        $connection->transactional(static function (Connection $connection): void {
            foreach (MigrationRuntime::BASELINE_VERSIONS as $version) {
                $connection->insert(MigrationRuntime::LEDGER_TABLE, [
                    'version' => $version,
                    'executed_at' => date('Y-m-d H:i:s'),
                    'execution_time' => 0,
                ]);
            }
        });

        return true;
    }

    private function assertTransactionalLedger(Connection $connection): void
    {
        $engine = $connection->fetchOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = BINARY DATABASE() AND TABLE_NAME = ?',
            [MigrationRuntime::LEDGER_TABLE]
        );
        if ($engine !== 'InnoDB') {
            throw new RuntimeException('Enrollment requires an InnoDB migration ledger. Review its storage engine before retrying.');
        }
    }

    private function verifyEvidence(Connection $connection, string $database, string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Provide a readable local enrollment evidence file.');
        }
        $contents = file_get_contents($path, false, null, 0, 65537);
        if ($contents === false || strlen($contents) > 65536) {
            throw new RuntimeException('The enrollment evidence file must be a JSON document up to 64 KiB.');
        }
        $evidence = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $parameters = $connection->getParams();
        if (!is_array($evidence) || ($evidence['database'] ?? null) !== $database
            || ($evidence['host'] ?? null) !== $parameters['host'] || ($evidence['port'] ?? null) !== $parameters['port']) {
            throw new RuntimeException('Enrollment evidence must identify the selected database host, port and name.');
        }
        if (!is_string($evidence['reference'] ?? null) || trim($evidence['reference']) === '') {
            throw new RuntimeException('Enrollment evidence must cite a reviewed deployment or import record.');
        }

        $source = $evidence['source'] ?? null;
        if ($source === 'legacy-migrations' && ($evidence['kyc_backfill_verified'] ?? null) === true) {
            return;
        }
        if ($source === 'schema-sql' && ($evidence['imported_into_empty_database'] ?? null) === true
            && ($evidence['schema_sha256'] ?? null) === self::SQL_BASELINE_SHA256) {
            return;
        }
        throw new RuntimeException('Provide evidence of the successful legacy KYC backfill or of the adoption schema.sql imported into an empty database.');
    }

    private function verifySchema(Connection $connection): void
    {
        foreach (['triggers' => ['TRIGGERS', 'TRIGGER_SCHEMA', 'TRIGGER_NAME'],
            'routines' => ['ROUTINES', 'ROUTINE_SCHEMA', 'ROUTINE_NAME'],
            'events' => ['EVENTS', 'EVENT_SCHEMA', 'EVENT_NAME']] as $kind => [$table, $schema, $name]) {
            $objects = $connection->fetchFirstColumn("SELECT {$name} FROM information_schema.{$table} WHERE {$schema} = BINARY DATABASE()");
            if ($objects !== []) {
                throw new RuntimeException("Unexpected database {$kind}: " . implode(', ', $objects) . '. Review these objects before enrollment.');
            }
        }
        $snapshot = file_get_contents(__DIR__ . '/enrollment-schema.sql');
        preg_match_all('/CREATE TABLE `([^`]+)` \(.*?\) ENGINE=.*?;/s', $snapshot, $matches, PREG_SET_ORDER);
        $definitions = [];
        foreach ($matches as $match) {
            $definitions[$match[1]] = rtrim($match[0], ';');
        }
        $tables = $connection->fetchAllKeyValue('SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = BINARY DATABASE()');
        $expected = array_keys($definitions);
        $missing = array_diff($expected, array_keys($tables));
        $unexpected = array_diff(array_keys($tables), [...$expected, MigrationRuntime::LEDGER_TABLE]);
        if ($missing !== [] || $unexpected !== []) {
            throw new RuntimeException('Unexpected database objects. Missing: [' . implode(', ', $missing)
                . ']. Extra: [' . implode(', ', $unexpected) . ']. Review the database schema before enrollment.');
        }

        foreach ($definitions as $table => $expectedDefinition) {
            if ($tables[$table] !== 'BASE TABLE') {
                throw new RuntimeException("Expected {$table} to be a base table. Review the database schema before enrollment.");
            }
            $identifier = $connection->getDatabasePlatform()->quoteSingleIdentifier($table);
            $definition = $connection->fetchNumeric("SHOW CREATE TABLE {$identifier}")[1];
            $normalized = preg_replace('/(\n\) ENGINE=\w+) AUTO_INCREMENT=\d+(?= DEFAULT CHARSET=)/', '$1', $definition);
            if ($table === 'students') {
                $normalized = $this->normalizeMemberEnums($normalized, $expectedDefinition);
            }
            if ($table === 'settings' && !str_contains($normalized, '  `id` ')) {
                $expectedDefinition = str_replace([
                    "  `id` int(11) NOT NULL AUTO_INCREMENT,\n",
                    "  `setting_group` varchar(50) DEFAULT 'general',\n",
                    "  PRIMARY KEY (`id`),\n  UNIQUE KEY `setting_key` (`setting_key`)",
                    'COLLATE=utf8mb4_general_ci',
                ], ['', '', '  PRIMARY KEY (`setting_key`)', 'COLLATE=utf8mb4_unicode_ci'], $expectedDefinition);
                // The historical CREATE TABLE omitted NOT NULL for this timestamp.
                $normalized = str_replace('`updated_at` timestamp NULL DEFAULT', '`updated_at` timestamp NOT NULL DEFAULT', $normalized);
            }
            if ($normalized !== $expectedDefinition) {
                throw new RuntimeException("Unsupported schema for {$table}. Compare SHOW CREATE TABLE with the documented baseline before enrollment.");
            }
        }
    }

    private function normalizeMemberEnums(string $definition, string $expected): string
    {
        foreach (['gender', 'status', 'housing_type'] as $column) {
            $pattern = '/^  `' . $column . '` (enum\((?:[^\n])+)$/m';
            preg_match($pattern, $expected, $canonical);
            preg_match($pattern, $definition, $actual);
            if (!$actual) {
                continue;
            }
            // Migration 06 retained enum order, nullability, defaults, collation and comments.
            // Accept the known member values in any order; require every adopted value.
            $enumValues = "((?:'(?:[^']|'')*',)*'(?:[^']|'')*')";
            $enum = "/^enum\(" . $enumValues . "\)(?: CHARACTER SET utf8mb4)?(?: COLLATE utf8mb4_(?:general|unicode)_ci)? (?:NOT NULL|DEFAULT NULL|DEFAULT '[^']*')(?: DEFAULT '[^']*')?(?: COMMENT '(?:[^'\\\\]|\\\\.|'')*')?,$/";
            if (!preg_match($enum, $actual[1], $values)) {
                continue;
            }
            preg_match('/^enum\(' . $enumValues . '\)/', $canonical[1], $canonicalValues);
            preg_match_all("/'((?:[^']|'')*)'/", $values[1], $actualValues);
            preg_match_all("/'((?:[^']|'')*)'/", $canonicalValues[1], $requiredValues);
            $known = $requiredValues[1];
            if (array_diff($known, $actualValues[1]) !== [] || array_diff($actualValues[1], $known) !== []) {
                continue;
            }
            if (preg_match("/ DEFAULT '([^']*)'/", $actual[1], $default) && !in_array($default[1], $known, true)) {
                continue;
            }
            if ($column !== 'housing_type' && !str_contains($actual[1], ' NOT NULL')) {
                continue;
            }
            $definition = str_replace($actual[0], $canonical[0], $definition);
        }
        return $definition;
    }

    private function verifyData(Connection $connection): void
    {
        $requiredKeys = ['contact_email', 'contact_phone', 'organization_name'];
        $keys = $connection->fetchFirstColumn('SELECT setting_key FROM settings WHERE setting_key IN (?, ?, ?)', $requiredKeys);
        $missingKeys = array_diff($requiredKeys, $keys);
        if ($missingKeys !== []) {
            throw new RuntimeException('Missing initial settings: ' . implode(', ', $missingKeys) . '. Review initial data before enrollment.');
        }
        if ($connection->fetchOne('SELECT 1 FROM users WHERE session_version < 1 OR role + 0 = 0 LIMIT 1')) {
            throw new RuntimeException('Found users with an invalid session version or role. Review staff user data before enrollment.');
        }
        if ($connection->fetchOne('SELECT 1 FROM students WHERE kyc_status + 0 = 0 OR gender + 0 = 0 OR status + 0 = 0 OR housing_type + 0 = 0 LIMIT 1')) {
            throw new RuntimeException('Found member records with invalid review status or member enum values. Review member data before enrollment.');
        }
    }
}
