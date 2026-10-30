<?php
namespace Amea\Service;

use InvalidArgumentException;

final class JournalMediaService
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private string $directory;
    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = realpath($projectRoot) ?: rtrim($projectRoot, '/');
        $this->directory = $this->root . '/uploads/journal/covers';
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
        foreach (['uploads', 'uploads/journal', 'uploads/journal/covers'] as $part) {
            if (is_link($this->root . '/' . $part)) {
                throw new \RuntimeException('Impossible de conserver l’image.');
            }
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Impossible de conserver l’image.');
        }
        if (!$this->safeDirectory()) {
            throw new \RuntimeException('Impossible de conserver l’image.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $this->directory . '/' . $name)) {
            throw new \RuntimeException('Impossible de conserver l’image.');
        }
        return ['path' => 'uploads/journal/covers/' . $name, 'mime' => $mime];
    }

    public function resolve(string $relativePath): ?array
    {
        if (!preg_match('#^uploads/journal/covers/[a-f0-9]{32}\.(jpg|png|webp)$#D', $relativePath)) {
            return null;
        }
        if (!$this->safeDirectory()) { return null; }
        $directory = realpath($this->directory);
        $candidate = $this->directory . '/' . basename($relativePath);
        if (is_link($candidate)) { return null; }
        $file = realpath($candidate);
        if ($directory === false || $file === false || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR) || !is_file($file)) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        $image = @getimagesize($file);
        if (!isset(self::TYPES[$mime]) || ($image['mime'] ?? null) !== $mime || !str_ends_with($file, '.' . self::TYPES[$mime])) {
            return null;
        }
        return ['path' => $file, 'mime' => $mime, 'length' => filesize($file)];
    }

    private function safeDirectory(): bool
    {
        foreach (['uploads', 'uploads/journal', 'uploads/journal/covers'] as $part) {
            if (is_link($this->root . '/' . $part)) { return false; }
        }
        return realpath($this->directory) === $this->directory;
    }

    public function discard(string $relativePath): void
    {
        $file = $this->resolve($relativePath);
        if ($file !== null) {
            @unlink($file['path']);
        }
    }
}
