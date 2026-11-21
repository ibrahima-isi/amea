<?php

namespace Amea\Storage;

use Aws\S3\S3Client;

class StorageFactory
{
    public static function fromEnvironment(string $projectRoot): Storage
    {
        $driver = self::environment('STORAGE_DRIVER', 'local');
        if ($driver === 'local') {
            return new LocalStorage($projectRoot);
        }
        if ($driver !== 's3') {
            throw new StorageException('STORAGE_DRIVER must be local or s3.');
        }
        $configuration = self::s3Configuration();
        $endpoint = parse_url($configuration['S3_ENDPOINT']);
        if ($endpoint === false || !in_array($endpoint['scheme'] ?? '', ['http', 'https'], true) || empty($endpoint['host']) || isset($endpoint['user']) || isset($endpoint['query']) || isset($endpoint['fragment'])) {
            throw new StorageException('S3_ENDPOINT must be an HTTP(S) endpoint without embedded credentials or query parameters.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $configuration['S3_BUCKET']) || str_contains($configuration['S3_BUCKET'], '..') || !preg_match('/^[a-z0-9-]+$/', $configuration['S3_REGION'])) {
            throw new StorageException('Invalid S3 bucket or region.');
        }
        $pathStyle = match (strtolower(self::environment('S3_PATH_STYLE', 'false'))) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new StorageException('S3_PATH_STYLE must be true, false, 1 or 0.'),
        };
        try {
            $client = new S3Client([
                'version' => '2006-03-01',
                'endpoint' => $configuration['S3_ENDPOINT'],
                'region' => $configuration['S3_REGION'],
                'credentials' => ['key' => $configuration['S3_ACCESS_KEY_ID'], 'secret' => $configuration['S3_SECRET_ACCESS_KEY']],
                'use_path_style_endpoint' => $pathStyle,
                // Portable providers differ in optional checksum support. Our SHA-256
                // metadata is computed from the accepted stream independently of ETags.
                'request_checksum_calculation' => 'when_required',
                'response_checksum_validation' => 'when_required',
                'http' => ['connect_timeout' => 5, 'timeout' => 120],
            ]);
            return new S3Storage($client, $configuration['S3_BUCKET']);
        } catch (\InvalidArgumentException $error) {
            throw new StorageException('Invalid S3 configuration.', 0, $error);
        }
    }

    /** Non-secret backend identity prevents replay against another bucket/root. */
    public static function identity(string $projectRoot): string
    {
        $driver = self::environment('STORAGE_DRIVER', 'local');
        if (!in_array($driver, ['local', 's3'], true)) {
            throw new StorageException('STORAGE_DRIVER must be local or s3.');
        }
        $configuration = $driver === 's3' ? self::s3Configuration() : [];
        $target = $driver === 'local' ? (realpath($projectRoot) ?: $projectRoot)
            : $configuration['S3_ENDPOINT'] . '\n' . $configuration['S3_BUCKET'];
        return hash('sha256', $driver . '\n' . $target);
    }

    private static function s3Configuration(): array
    {
        $aliases = [
            'S3_ENDPOINT' => 'R2_ENDPOINT',
            'S3_REGION' => null,
            'S3_BUCKET' => 'R2_BUCKET_NAME',
            'S3_ACCESS_KEY_ID' => 'R2_ACCESS_KEY_ID',
            'S3_SECRET_ACCESS_KEY' => 'R2_SECRET_ACCESS_KEY',
        ];
        $canonical = [];
        foreach ($aliases as $name => $alias) {
            $canonical[$name] = self::environment($name);
        }
        // Any provider-specific canonical input selects the whole canonical family.
        // S3_PATH_STYLE is shared and its Compose default must not select a family.
        $useCanonical = count(array_filter($canonical, fn (string $value): bool => $value !== '')) > 0;
        $configuration = [];
        foreach ($aliases as $name => $alias) {
            $configuration[$name] = $useCanonical ? $canonical[$name]
                : ($alias === null ? 'auto' : self::environment($alias));
            if (!$useCanonical && $name === 'S3_ENDPOINT' && $configuration[$name] === '') {
                $accountId = self::environment('R2_ACCOUNT_ID');
                if (!preg_match('/^[a-f0-9]{32}$/', $accountId)) {
                    throw new StorageException('R2_ENDPOINT or a valid 32-character lowercase hexadecimal R2_ACCOUNT_ID is required.');
                }
                $configuration[$name] = 'https://' . $accountId . '.r2.cloudflarestorage.com';
            }
            if (trim($configuration[$name]) === '') {
                throw new StorageException('The selected S3 backend requires ' . ($useCanonical ? $name : $alias) . '.');
            }
        }
        return $configuration;
    }

    private static function environment(string $name, string $default = ''): string
    {
        return (string) ($_ENV[$name] ?? (getenv($name) !== false ? getenv($name) : $default));
    }
}
