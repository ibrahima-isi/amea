<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;

class CommunicationController extends BaseController
{
    private StudentRepository $studentRepo;
    private EmailService $emailService;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!$this->auth->hasPermission('communications')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission d\'accéder aux communications.');
            $this->redirect('dashboard.php');
            return;
        }
        $this->studentRepo  = new StudentRepository(Container::db());
        $this->emailService = new EmailService(
            $_ENV['MAIL_USER'] ?? '',
            $_ENV['MAIL_PASS'] ?? '',
            'contact@aeesgs.org',
            'AEESGS',
            $this->view
        );
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('admin/pages/communications.html.twig', [
            'institutions' => $this->studentRepo->getDistinctInstitutions(),
            'stats' => $this->studentRepo->getConsentStats(),
        ]);
    }

    // ─── count (AJAX) ──────────────────────────────────────────────────────────

    public function count(): void
    {
        header('Content-Type: application/json');
        $filters = array_filter([
            'status'      => $_GET['status'] ?? '',
            'institution' => $_GET['institution'] ?? '',
        ]);
        echo json_encode(['count' => $this->studentRepo->countAll($filters)]);
        exit();
    }

    // ─── send ──────────────────────────────────────────────────────────────────

    public function send(): void
    {
        if (!$this->csrf->verify($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('communications.php');
            return;
        }

        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');

        if (empty($subject) || empty($body)) {
            $this->flash->set('error', 'Le sujet et le corps du message sont requis.');
            $this->redirect('communications.php');
            return;
        }

        $filters = array_filter([
            'status'      => $_POST['status'] ?? '',
            'institution' => $_POST['institution'] ?? '',
        ]);

        $students = $this->studentRepo->findAll($filters);
        $sent     = 0;
        foreach ($students as $student) {
            if ($student->getEmail()) {
                $this->emailService->send(
                    $student->getEmail(),
                    $subject,
                    nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))
                );
                $sent++;
            }
        }

        $this->flash->set('success', $sent . ' email(s) envoyé(s) avec succès.');
        $this->redirect('communications.php');
    }
}
