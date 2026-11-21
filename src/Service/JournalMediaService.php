<?php
namespace Amea\Service;

use InvalidArgumentException;

final class JournalMediaService
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private \Amea\Storage\Storage $storage;

    public function __construct(string $projectRoot, ?\Amea\Storage\Storage $storage = null)
    {
        $this->storage = $storage ?? \Amea\Storage\StorageFactory::fromEnvironment($projectRoot);
    }

    public function store(array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null)
            || !is_uploaded_file($upload['tmp_name']) || !is_int($upload['size'] ?? null)
            || $upload['size'] < 1 || $upload['size'] > 5 * 1024 * 1024
            || filesize($upload['tmp_name']) !== $upload['size']) {
            throw new InvalidArgumentException('L’image doit être un fichier valide de 5 Mio maximum.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $image = @getimagesize($upload['tmp_name']);
        if (!isset(self::TYPES[$mime]) || !$image || ($image['mime'] ?? null) !== $mime) {
            throw new InvalidArgumentException('Choisissez une image JPEG, PNG ou WebP valide.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $key = 'uploads/journal/covers/' . $name;
        $source = fopen($upload['tmp_name'], 'rb');
        try {
            $accepted = $this->storage->write($key, $source, $mime, time());
            if ($accepted['size'] !== $upload['size'] || $accepted['sha256'] !== hash_file('sha256', $upload['tmp_name'])) {
                throw new \Amea\Storage\StorageException('Incomplete journal upload.');
            }
            $verified = $this->open($key);
            fclose($verified['stream']);
        } finally {
            fclose($source);
        }
        return ['path' => 'uploads/journal/covers/' . $name, 'mime' => $mime];
    }

    /** Verified temporary stream, independent of the storage backend. */
    public function open(string $relativePath): array
    {
        if (!preg_match('#^uploads/journal/covers/[a-f0-9]{32}\.(jpg|png|webp)$#D', $relativePath)) {
            throw new \OutOfBoundsException('Publication introuvable.');
        }
        $file = (new MemberDocumentService($this->storage))->open($relativePath, array_keys(self::TYPES));
        $image = @getimagesize(stream_get_meta_data($file['stream'])['uri']);
        if (!$image || ($image['mime'] ?? null) !== $file['mime'] || pathinfo($relativePath, PATHINFO_EXTENSION) !== self::TYPES[$file['mime']]) {
            fclose($file['stream']);
            throw new \UnexpectedValueException('Image invalide.');
        }
        return $file;
    }

    /** Compatibility for callers inspecting a locally readable temporary file. */
    public function resolve(string $relativePath): ?array
    {
        try {
            $file = $this->open($relativePath);
            return $file + ['path' => stream_get_meta_data($file['stream'])['uri'], 'length' => $file['size']];
        } catch (\OutOfBoundsException|\UnexpectedValueException|\Amea\Storage\StorageException) {
            return null;
        }
    }

    public function discard(string $relativePath): void
    {
        if (preg_match('#^uploads/journal/covers/[a-f0-9]{32}\.(jpg|png|webp)$#D', $relativePath)) {
            $this->storage->delete($relativePath);
        }
    }
}
