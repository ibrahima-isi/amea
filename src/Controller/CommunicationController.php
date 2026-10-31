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
        if (!\hasPermission('communications')) {
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
            'stats' => $this->getStats(),
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
        $result = $this->studentRepo->findPaginated(1, PHP_INT_MAX, $filters);
        echo json_encode(['count' => $result['total']]);
        exit();
    }

    // ─── send ──────────────────────────────────────────────────────────────────

    public function send(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
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

        $result = $this->studentRepo->findPaginated(1, PHP_INT_MAX, $filters);
        $sent   = 0;
        foreach ($result['items'] as $student) {
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

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function getStats(): array
    {
        $all = $this->studentRepo->findAll();
        return [
            'total'         => count($all),
            'consented'     => count(array_filter($all, fn($s) => $s->hasConsentPrivacy())),
            'not_consented' => count(array_filter($all, fn($s) => !$s->hasConsentPrivacy())),
        ];
    }
}
