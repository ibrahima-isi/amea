<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\EmailService;

class AcceptCguController extends BaseController
{
    private StudentRepository $studentRepo;
    private SettingRepository $settingRepo;
    private EmailService      $emailService;

    public function __construct()
    {
        parent::__construct();
        $db = Container::db();
        $this->studentRepo  = new StudentRepository($db);
        $this->settingRepo  = new SettingRepository($db);
        $this->emailService = new EmailService(
            $_ENV['MAIL_USER'] ?? '',
            $_ENV['MAIL_PASS'] ?? '',
            'noreply@aeesgs.org',
            'AEESGS'
        );
    }

    public function accept(): void
    {
        $token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
        $student = $token !== '' ? $this->studentRepo->findByToken($token) : null;

        if (!$student) {
            $this->render('accept-cgu.html.twig', ['state' => 'invalid', 'token' => $token, 'student' => null]);
            return;
        }

        $state = $student->hasConsentPrivacy() ? 'update' : 'pending';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->render('accept-cgu.html.twig', ['state' => 'invalid', 'token' => $token, 'student' => null]);
                return;
            }

            $action = $_POST['action'] ?? '';

            if ($action === 'accept') {
                $this->studentRepo->update($student->getId(), ['consent_privacy' => 1]);
                $state = 'accepted';

            } elseif ($action === 'refuse') {
                $state = 'refused';

            } elseif ($action === 'request_deletion') {
                $adminEmail = $this->settingRepo->getValue('contact_email', 'contact@aeesgs.org');
                $body = '<p>Le membre <strong>'
                    . htmlspecialchars($student->getFullName(), ENT_QUOTES, 'UTF-8')
                    . '</strong> (email&nbsp;: <strong>'
                    . htmlspecialchars($student->getEmail(), ENT_QUOTES, 'UTF-8')
                    . '</strong>, ID&nbsp;: #' . $student->getId() . ')'
                    . ' a refusé les CGU et demande la suppression de ses données personnelles.</p>';
                $this->emailService->send(
                    $adminEmail,
                    'AEESGS – Demande de suppression de données (#' . $student->getId() . ')',
                    $body
                );
                $state = 'deletion_requested';
            }
        }

        $this->render('accept-cgu.html.twig', [
            'state'   => $state,
            'token'   => $token,
            'student' => $student,
        ]);
    }
}
