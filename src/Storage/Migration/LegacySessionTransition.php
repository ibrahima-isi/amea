<?php

declare(strict_types=1);

namespace Amea\Storage\Migration;

use Amea\Storage\StorageException;

/**
 * Transfers unfinished public registrations from a legacy single-app session
 * store into the split public session store while excluding all legacy staff
 * authentication state.
 */
final class LegacySessionTransition
{
    /** @var list<string> */
    private const ALLOWED_PUBLIC_KEYS = [
        'pending_registration',
        'csrf_token',
        'last_activity',
        'flash',
    ];

    /**
     * @param array<string, array{sha256: string, size: int, mtime: int}> $capturedFiles
     * @return array{public: array<string, array{sha256: string, size: int, mtime: int}>, staff: array<string, array{sha256: string, size: int, mtime: int}>}
     */
    public static function restoreSingleToSplit(
        string $sourceDirectory,
        string $publicDestination,
        array $capturedFiles,
        string $serializer
    ): array {
        if (realpath($publicDestination) !== $publicDestination || is_link($publicDestination) || !is_dir($publicDestination)) {
            throw new StorageException('Split restore requires an existing canonical public session directory.');
        }

        $restoredPublic = [];
        foreach ($capturedFiles as $relative => $metadata) {
            if (!str_starts_with(basename($relative), 'sess_')) {
                continue;
            }
            $sourcePath = $sourceDirectory . '/' . $relative;
            $raw = @file_get_contents($sourcePath);
            if ($raw === false) {
                throw new StorageException('Unable to read captured legacy session record.');
            }

            $data = ReferenceAudit::decodeSessionBytes($raw, $serializer);
            if (!isset($data['pending_registration']) || !is_array($data['pending_registration'])) {
                continue;
            }

            $sanitized = [];
            foreach (self::ALLOWED_PUBLIC_KEYS as $key) {
                if (array_key_exists($key, $data)) {
                    $sanitized[$key] = $data[$key];
                }
            }

            $encoded = self::encodeSessionBytes($sanitized, $serializer);
            $targetPath = $publicDestination . '/' . $relative;
            if (file_exists($targetPath) || is_link($targetPath)) {
                throw new StorageException('Restore cannot overwrite an existing public session file.');
            }
            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0700, true);
            }

            $handle = @fopen($targetPath, 'xb');
            if ($handle === false || !chmod($targetPath, 0600)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                throw new StorageException('Unable to create restored public session file.');
            }
            try {
                if (fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle) || !fsync($handle)) {
                    throw new StorageException('Unable to persist restored public session bytes.');
                }
            } finally {
                fclose($handle);
            }

            if (!touch($targetPath, (int) $metadata['mtime'])) {
                throw new StorageException('Unable to preserve restored public session timestamp.');
            }

            $restoredPublic[$relative] = [
                'sha256' => hash('sha256', $encoded),
                'size' => strlen($encoded),
                'mtime' => (int) $metadata['mtime'],
            ];
        }

        ksort($restoredPublic);

        return ['public' => $restoredPublic, 'staff' => []];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encodeSessionBytes(array $data, string $serializer): string
    {
        if ($serializer === 'php_serialize') {
            return serialize($data);
        }

        $encoded = '';
        foreach ($data as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new StorageException('Session keys must be non-empty strings.');
            }
            if ($serializer === 'php_binary') {
                if (strlen($key) > 127) {
                    throw new StorageException('Binary session key exceeds 127 bytes.');
                }
                $encoded .= chr(strlen($key)) . $key . serialize($value);
            } elseif ($serializer === 'php') {
                if (str_contains($key, '|')) {
                    throw new StorageException('Invalid session key for php serializer.');
                }
                $encoded .= $key . '|' . serialize($value);
            } else {
                throw new StorageException('Unsupported session serializer.');
            }
        }

        return $encoded;
    }
}
