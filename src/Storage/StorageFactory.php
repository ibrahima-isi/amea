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
        $configuration = [];
        foreach (['S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY'] as $name) {
            $configuration[$name] = self::environment($name);
            if (trim($configuration[$name]) === '') {
                throw new StorageException('The selected S3 backend requires ' . $name . '.');
            }
        }
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

    private static function environment(string $name, string $default = ''): string
    {
        return (string) ($_ENV[$name] ?? (getenv($name) !== false ? getenv($name) : $default));
    }
}
