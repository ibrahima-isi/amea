<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Service\EmailService;
use Amea\Service\PasswordResetService;

class PasswordResetController extends BaseController
{
    private PasswordResetService $resetService;
    private EmailService $emailService;

    public function __construct()
    {
        parent::__construct();
        $this->emailService = new EmailService(
            $_ENV['MAIL_USER'] ?? '',
            $_ENV['MAIL_PASS'] ?? '',
            'noreply@aeesgs.org',
            'AEESGS'
        );
        $this->resetService = new PasswordResetService(
            Container::db(),
            (string)($_ENV['STAFF_APP_URL'] ?? $_ENV['APP_URL'] ?? 'http://localhost'),
            __DIR__ . '/../../'
        );
    }

    // ─── forgotForm ────────────────────────────────────────────────────────────

    public function forgotForm(): void
    {
        $this->render('forgot-password.html.twig', []);
    }

    // ─── forgotSubmit ──────────────────────────────────────────────────────────

    public function forgotSubmit(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('forgot-password.php');
            return;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash->set('error', 'Veuillez fournir une adresse e-mail valide.');
            $this->redirect('forgot-password.php');
            return;
        }

        $sendMail = fn(string $to, string $subject, string $body): bool =>
            $this->emailService->send($to, $subject, $body);

        $this->resetService->requestForEmail($email, $sendMail);

        $this->flash->set('success', 'Si un compte actif correspond à cette adresse, un lien de réinitialisation valable 5 minutes sera envoyé.');
        $this->redirect('login.php');
    }

    // ─── resetForm ─────────────────────────────────────────────────────────────

    public function resetForm(): void
    {
        $token = trim($_GET['token'] ?? '');

        if (empty($token) || !$this->resetService->isTokenUsable($token)) {
            $this->flash->set('error', 'Ce lien de réinitialisation est invalide ou a expiré.');
            $this->redirect('forgot-password.php');
            return;
        }

        $this->render('reset-password.html.twig', ['token' => $token]);
    }

    // ─── resetSubmit ───────────────────────────────────────────────────────────

    public function resetSubmit(): void
    {
        $token = trim($_POST['token'] ?? '');

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('forgot-password.php');
            return;
        }

        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $sendMail = fn(string $to, string $subject, string $body): bool =>
            $this->emailService->send($to, $subject, $body);

        $result = $this->resetService->resetPassword($token, $password, $confirmPassword, $sendMail);

        if (!$result['success']) {
            $this->flash->set('error', $result['message']);
            $this->redirect('reset-password.php?token=' . urlencode($token));
            return;
        }

        $this->flash->set('success', $result['message']);
        $this->redirect('login.php');
    }
}
