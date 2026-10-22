<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\StudentRepository;

class DownloadController extends BaseController
{
    private StudentRepository $studentRepo;

    public function __construct()
    {
        parent::__construct();
        $this->studentRepo = new StudentRepository(Container::db());
    }

    public function serve(): void
    {
        $this->requireAdmin();

        $id   = (int)($_GET['id'] ?? 0);
        $type = $_GET['type'] ?? '';

        if ($id <= 0 || !in_array($type, ['identite', 'cv'], true)) {
            http_response_code(400);
            exit('Paramètres invalides.');
        }

        $student = $this->studentRepo->findById($id);

        if (!$student) {
            http_response_code(404);
            exit('Étudiant introuvable.');
        }

        $filePath = $type === 'identite'
            ? $student->getIdentityDocument()
            : $student->getCvPath();

        if (empty($filePath)) {
            http_response_code(404);
            exit('Aucun document disponible.');
        }

        $absolutePath = __DIR__ . '/../../' . ltrim($filePath, '/');

        if (!is_file($absolutePath)) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        $uploadsDir = realpath(__DIR__ . '/../../uploads');
        $realFile   = realpath($absolutePath);

        if ($uploadsDir === false || $realFile === false || !str_starts_with($realFile, $uploadsDir . DIRECTORY_SEPARATOR)) {
            http_response_code(403);
            exit('Accès interdit.');
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $realFile);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            http_response_code(415);
            exit('Type de fichier non supporté.');
        }

        $ext       = pathinfo($realFile, PATHINFO_EXTENSION);
        $label     = $type === 'identite' ? 'identite' : 'cv';
        $cleanName = preg_replace('/[^a-z0-9_\-]/i', '_',
            $student->getFirstName() . '_' . $student->getLastName() . '_' . $label) . '.' . $ext;

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        $disposition = (($_GET['inline'] ?? '') === '1') ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $cleanName . '"');
        header('Content-Length: ' . filesize($realFile));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($realFile);
        exit();
    }
}
