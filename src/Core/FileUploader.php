<?php
namespace Amea\Core;

class FileUploader
{
    private const ALLOWED_MIMES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf',
    ];

    public function __construct(private string $projectRoot, private ?\Amea\Storage\Storage $storage = null) {}

    /**
     * @return array{success: bool, filepath: string|null, message: string|null}
     */
    public function handle(
        array  $fileInput,
        array  $allowedExtensions,
        int    $maxBytes,
        string $uploadDir
    ): array {
        if (!isset($fileInput['error']) || $fileInput['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'filepath' => null, 'message' => 'Aucun fichier fourni.'];
        }
        if ($fileInput['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'filepath' => null, 'message' => 'Erreur lors du téléchargement.'];
        }
        if ($fileInput['size'] > $maxBytes) {
            $mb = round($maxBytes / 1048576, 1);
            return ['success' => false, 'filepath' => null, 'message' => "Fichier trop volumineux (max {$mb} Mo)."];
        }

        $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return ['success' => false, 'filepath' => null, 'message' => 'Extension non autorisée.'];
        }

        // MIME verification
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $fileInput['tmp_name']);
        $expectedMime = self::ALLOWED_MIMES[$ext] ?? null;
        if ($expectedMime && $mime !== $expectedMime) {
            return ['success' => false, 'filepath' => null, 'message' => 'Type MIME invalide.'];
        }

        if ($this->storage !== null) {
            if (!is_uploaded_file($fileInput['tmp_name']) || filesize($fileInput['tmp_name']) !== (int)$fileInput['size']) {
                return ['success' => false, 'filepath' => null, 'message' => 'Échec du déplacement du fichier.'];
            }
            $key = trim($uploadDir, '/') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
            $stream = fopen($fileInput['tmp_name'], 'rb');
            try {
                $accepted = $this->storage->write($key, $stream, $mime, time());
                $metadata = $this->storage->stat($key);
                if ($accepted['size'] !== (int)$fileInput['size'] || $accepted['sha256'] !== hash_file('sha256', $fileInput['tmp_name'])
                    || $metadata === null || $metadata['size'] !== $accepted['size'] || $metadata['sha256'] !== $accepted['sha256']) {
                    throw new \Amea\Storage\StorageException('Incomplete upload.');
                }
                return ['success' => true, 'filepath' => $key, 'message' => null];
            } catch (\Amea\Storage\StorageException $error) {
                error_log('[FileUploader] ' . $error->getMessage());
                return ['success' => false, 'filepath' => null, 'message' => 'Échec du déplacement du fichier.', 'retryable' => true];
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $uploadPath = rtrim($this->projectRoot, '/') . '/' . ltrim($uploadDir, '/');
        if (!is_dir($uploadPath) && !mkdir($uploadPath, 0755, true)) {
            return ['success' => false, 'filepath' => null, 'message' => 'Répertoire de destination inaccessible.'];
        }

        $filename    = uniqid('', true) . '.' . $ext;
        $destination = $uploadPath . '/' . $filename;

        if (!move_uploaded_file($fileInput['tmp_name'], $destination)) {
            return ['success' => false, 'filepath' => null, 'message' => 'Échec du déplacement du fichier.'];
        }

        $relativePath = ltrim($uploadDir, '/') . '/' . $filename;
        return ['success' => true, 'filepath' => $relativePath, 'message' => null];
    }

    /**
     * Safely delete a file, ensuring it is within the project's uploads directory.
     */
    public function safeDelete(?string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }
        if ($this->storage !== null) {
            try {
                $this->storage->delete($filePath);
                return true;
            } catch (\Amea\Storage\StorageException) {
                return false;
            }
        }
        $uploadsDir = realpath(rtrim($this->projectRoot, '/') . '/uploads');
        $absolute   = realpath(rtrim($this->projectRoot, '/') . '/' . ltrim($filePath, '/'));
        if ($uploadsDir === false || $absolute === false) {
            return false;
        }
        if (!str_starts_with($absolute, $uploadsDir)) {
            error_log("[FileUploader] Path traversal attempt blocked: {$filePath}");
            return false;
        }
        if (!is_file($absolute)) {
            return false;
        }
        return unlink($absolute);
    }

    /**
     * Finalize a previously-staged upload and return the new
     * relative path. Used to finalize files only once a registration is confirmed.
     * Injected storage retains its source for transaction-owned cleanup.
     * Both source and destination are constrained to the uploads/ tree.
     */
    public function finalize(?string $stagedRelPath, string $targetDir): ?string
    {
        if (empty($stagedRelPath)) {
            return null;
        }
        if ($this->storage !== null) {
            $destination = trim($targetDir, '/') . '/' . basename($stagedRelPath);
            try {
                $this->storage->copy($stagedRelPath, $destination);
                $reader = new \Amea\Service\MemberDocumentService($this->storage);
                $source = $reader->open($stagedRelPath, array_values(self::ALLOWED_MIMES));
                try {
                    $final = $reader->open($destination, array_values(self::ALLOWED_MIMES));
                    try {
                        $sourceHash = hash_init('sha256');
                        $finalHash = hash_init('sha256');
                        hash_update_stream($sourceHash, $source['stream']);
                        hash_update_stream($finalHash, $final['stream']);
                        if ($source['size'] !== $final['size'] || hash_final($sourceHash) !== hash_final($finalHash)) {
                            throw new \Amea\Storage\StorageException('Finalized content mismatch.');
                        }
                    } finally {
                        fclose($final['stream']);
                    }
                } finally {
                    fclose($source['stream']);
                }
                return $destination;
            } catch (\Amea\Storage\StorageException | \OutOfBoundsException | \UnexpectedValueException) {
                return null;
            }
        }
        $uploadsDir = realpath(rtrim($this->projectRoot, '/') . '/uploads');
        $source     = realpath(rtrim($this->projectRoot, '/') . '/' . ltrim($stagedRelPath, '/'));
        if ($uploadsDir === false || $source === false || !str_starts_with($source, $uploadsDir)) {
            return null;
        }
        if (!is_file($source)) {
            return null;
        }

        $targetDir = trim($targetDir, '/');
        $destDir   = rtrim($this->projectRoot, '/') . '/' . $targetDir;
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return null;
        }

        $filename = basename($source);
        if (!rename($source, $destDir . '/' . $filename)) {
            return null;
        }
        return $targetDir . '/' . $filename;
    }
}
