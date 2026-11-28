<?php

declare(strict_types=1);

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\Runtime;
use Amea\Repository\ConsentRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\ConsentService;
use Amea\Service\EmailService;

class AcceptTermsController extends BaseController
{
    private ConsentService $consentService;

    public function __construct()
    {
        parent::__construct();
        $db = Container::db();
        $emailService = new EmailService(
            Runtime::env('MAIL_USER'),
            Runtime::env('MAIL_PASS'),
            'noreply@aeesgs.org',
            'AEESGS'
        );
        $this->consentService = new ConsentService(
            new ConsentRepository($db),
            null,
            new StudentRepository($db),
            new SettingRepository($db),
            $emailService
        );
    }

    public function accept(): void
    {
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
        $student = $token !== '' ? $this->consentService->findMemberByCguToken($token) : null;

        if (!$student) {
            $this->render('accept-cgu.html.twig', ['state' => 'invalid', 'token' => $token, 'student' => null]);
            return;
        }

        $state = $student->hasConsentPrivacy() ? 'update' : 'pending';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
                $this->render('accept-cgu.html.twig', ['state' => 'invalid', 'token' => $token, 'student' => null]);
                return;
            }

            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'accept') {
                $state = $this->consentService->handleMemberAcceptance($student) ? 'accepted' : 'invalid';
            } elseif ($action === 'refuse') {
                $state = $this->consentService->handleMemberRefusal($student) ? 'refused' : 'invalid';
            } elseif ($action === 'request_deletion') {
                $state = $this->consentService->handleMemberDeletionRequest($student) ? 'deletion_requested' : 'invalid';
            }
        }

        $this->render('accept-cgu.html.twig', [
            'state' => $state,
            'token' => $token,
            'student' => $student,
        ]);
    }
}
