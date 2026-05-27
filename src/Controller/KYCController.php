<?php

namespace Amea\Controller;

use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;
use Amea\Core\TemplateEngine;

class KYCController extends BaseController
{
    private StudentRepository $studentRepo;
    private EmailService $emailService;

    public function __construct()
    {
        parent::__construct();
        $this->checkAdmin();
        
        $db = \Amea\Config\Database::fromEnv()->getConnection();
        $this->studentRepo = new StudentRepository($db);
        
        $projectRoot = __DIR__ . '/../../';
        $this->emailService = new EmailService(
            $_ENV['MAIL_USER'] ?? '',
            $_ENV['MAIL_PASS'] ?? '',
            'noreply@aeesgs.org',
            'AEESGS Platform',
            new TemplateEngine($projectRoot)
        );
    }

    private function checkAdmin(): void
    {
        if (!$this->session->isLoggedIn() || $this->session->role() !== 'admin') {
            $this->flash->add('error', 'Accès refusé. Réservé aux administrateurs.');
            $this->redirect('/login.php');
        }
    }

    public function index(): void
    {
        $filters = ['kyc_status' => 'UNDER_REVIEW'];
        $students = $this->studentRepo->findAll($filters); // StudentRepository::findAll needs to support filters

        $this->render('admin/pages/kyc-list.html.twig', [
            'students' => $students,
        ]);
    }

    public function review(int $id): void
    {
        $student = $this->studentRepo->findById($id);
        if (!$student) {
            $this->flash->add('error', 'Étudiant introuvable.');
            $this->redirect('kyc-list.php');
            return;
        }

        $this->render('admin/pages/kyc-detail.html.twig', [
            'student' => $student,
        ]);
    }

    public function decide(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('kyc-detail.php?id=' . $id);
            return;
        }

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->add('error', 'Session expirée.');
            $this->redirect('kyc-detail.php?id=' . $id);
            return;
        }

        $action = $_POST['action'] ?? '';
        $notes  = trim($_POST['kyc_notes'] ?? '');

        $student = $this->studentRepo->findById($id);
        if (!$student) {
            $this->flash->add('error', 'Étudiant introuvable.');
            $this->redirect('kyc-list.php');
            return;
        }

        switch ($action) {
            case 'approve':
                $this->approve($student);
                break;
            case 'clarify':
                $this->requestClarification($student, $notes);
                break;
            case 'reject':
                $this->reject($student, $notes);
                break;
            default:
                $this->flash->add('error', 'Action invalide.');
                $this->redirect('kyc-detail.php?id=' . $id);
        }
    }

    private function approve($student): void
    {
        $this->studentRepo->update($student->getId(), [
            'kyc_status' => 'APPROVED',
            'is_locked'  => 1,
            'kyc_notes'  => null,
        ]);

        $this->emailService->sendFromTemplate(
            $student->getEmail(),
            'Votre inscription a été approuvée – AEESGS',
            'emails/registration-approved.html',
            ['student' => $student]
        );

        $this->flash->add('success', 'Dossier approuvé avec succès.');
        $this->redirect('kyc-list.php');
    }

    private function requestClarification($student, string $notes): void
    {
        if (empty($notes)) {
            $this->flash->add('error', 'Veuillez préciser les informations manquantes.');
            $this->redirect('kyc-detail.php?id=' . $student->getId());
            return;
        }

        // Generate token if not exists
        $token = $student->getReviewToken() ?: \bin2hex(random_bytes(32));

        $this->studentRepo->update($student->getId(), [
            'kyc_status'   => 'NEEDS_CLARIFICATION',
            'kyc_notes'    => $notes,
            'is_locked'    => 0, // Unlock for student edit
            'review_token' => $token,
        ]);

        $this->emailService->sendFromTemplate(
            $student->getEmail(),
            'Informations complémentaires requises – AEESGS',
            'emails/registration-clarification.html',
            [
                'student' => $student,
                'notes'   => $notes,
                'link'    => $_ENV['APP_URL'] . '/kyc-correction.php?token=' . $token
            ]
        );

        $this->flash->add('warning', 'Demande de clarification envoyée.');
        $this->redirect('kyc-list.php');
    }

    private function reject($student, string $notes): void
    {
        $this->studentRepo->update($student->getId(), [
            'kyc_status' => 'REJECTED',
            'kyc_notes'  => $notes,
            'is_locked'  => 1,
        ]);

        $this->emailService->sendFromTemplate(
            $student->getEmail(),
            'Votre dossier d\'inscription a été rejeté – AEESGS',
            'emails/registration-rejected.html',
            ['student' => $student, 'notes' => $notes]
        );

        $this->flash->add('error', 'Dossier rejeté.');
        $this->redirect('kyc-list.php');
    }
}
