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

    public function __construct(private string $projectRoot) {}

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
}
