<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\FileUploader;
use Amea\Repository\StudentRepository;
use Amea\Service\StudentService;

class TrashController extends BaseController
{
    private StudentRepository $studentRepo;
    private StudentService $studentService;
    private int $retentionDays;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!$this->auth->hasPermission('students')) {
            $this->flash->set('error', "Accès refusé : vous n'avez pas la permission de gérer les étudiants.");
            $this->redirect('dashboard.php');
        }
        $this->studentRepo = new StudentRepository(Container::db());
        $this->studentService = new StudentService($this->studentRepo, new FileUploader(__DIR__ . '/../../'));
        $this->retentionDays = max(1, (int)($_ENV['TRASH_RETENTION_DAYS'] ?? 30));
    }

    public function index(): void
    {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;

        $result = $this->studentRepo->findTrashedPaginated($page, $perPage);
        $total  = $result['total'];
        $now    = new \DateTime();

        // Pair each student with how many days remain before automatic purge.
        $items = [];
        foreach ($result['items'] as $student) {
            $daysRemaining = $this->retentionDays;
            if ($student->getDeletedAt()) {
                $expiry = (new \DateTime($student->getDeletedAt()))->modify("+{$this->retentionDays} days");
                $daysRemaining = max(0, (int)ceil(($expiry->getTimestamp() - $now->getTimestamp()) / 86400));
            }
            $items[] = ['student' => $student, 'days_remaining' => $daysRemaining];
        }

        $this->render('admin/pages/trash.html.twig', [
            'items'         => $items,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => $perPage,
            'totalPages'    => (int)ceil(max(1, $total) / $perPage),
            'retentionDays' => $this->retentionDays,
        ]);
    }

    public function handlePost(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('trash.php');
            return;
        }

        $id     = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($action === 'restore') {
            $this->restore($id);
        } elseif ($action === 'force_delete') {
            $this->forceDelete($id);
        }

        $this->redirect('trash.php');
    }

    private function restore(int $id): void
    {
        if ($this->studentRepo->restore($id)) {
            $this->flash->set('success', 'Étudiant restauré avec succès.');
        } else {
            $this->flash->set('error', 'Restauration impossible : étudiant introuvable dans la corbeille.');
        }
    }

    private function forceDelete(int $id): void
    {
        $student = $this->studentRepo->findTrashedById($id);
        if (!$student) {
            $this->flash->set('error', 'Étudiant introuvable dans la corbeille.');
            return;
        }

        $this->studentService->purgeTrashedStudent($student);
        $this->flash->set('success', 'Étudiant définitivement supprimé.');
    }
}
