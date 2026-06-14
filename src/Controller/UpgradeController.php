<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\StudentRepository;

class UpgradeController extends BaseController
{
    private StudentRepository $studentRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!\hasPermission('upgrade')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer la mise à niveau des membres.');
            $this->redirect('dashboard.php');
            return;
        }
        $this->studentRepo = new StudentRepository(Container::db());
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('admin/pages/upgrade-levels.html.twig', [
            'students' => $this->studentRepo->findAll(),
        ]);
    }

    // ─── confirm ───────────────────────────────────────────────────────────────

    public function confirm(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('upgrade-levels.php');
            return;
        }

        $id        = (int)($_POST['student_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        if (!in_array($newStatus, ['ELEVE', 'ETUDIANT', 'STAGIAIRE'], true)) {
            $this->flash->set('error', 'Statut invalide.');
            $this->redirect('upgrade-levels.php');
            return;
        }

        if ($id <= 0 || !$this->studentRepo->findById($id)) {
            $this->flash->set('error', 'Membre introuvable.');
            $this->redirect('upgrade-levels.php');
            return;
        }

        $this->studentRepo->update($id, ['status' => $newStatus]);
        $this->flash->set('success', 'Statut du membre mis à jour avec succès.');
        $this->redirect('upgrade-levels.php');
    }
}
