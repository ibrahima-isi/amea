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

        try {
            $reader = new \Amea\Service\MemberDocumentService(Container::storage());
            $document = $reader->open($filePath, ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
        } catch (\OutOfBoundsException) {
            http_response_code(404);
            exit('Fichier introuvable.');
        } catch (\UnexpectedValueException) {
            http_response_code(415);
            exit('Type de fichier non supporté.');
        } catch (\Amea\Storage\StorageException) {
            http_response_code(503);
            exit('Document temporairement indisponible.');
        }
        $mimeType = $document['mime'];
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $label     = $type === 'identite' ? 'identite' : 'cv';
        $cleanName = preg_replace('/[^a-z0-9_\-]/i', '_',
            $student->getFirstName() . '_' . $student->getLastName() . '_' . $label) . '.' . $ext;

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        $disposition = (($_GET['inline'] ?? '') === '1') ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $cleanName . '"');
        header('Content-Length: ' . $document['size']);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        fpassthru($document['stream']);
        fclose($document['stream']);
        exit();
    }
}
