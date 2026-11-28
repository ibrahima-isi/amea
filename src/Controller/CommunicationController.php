<?php

declare(strict_types=1);

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\Runtime;
use Amea\Model\CommunicationStatus;
use Amea\Model\CommunicationType;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Repository\UserRepository;
use Amea\Service\CommunicationService;
use Amea\Service\EmailService;
use Amea\Service\MailQuotaService;

class CommunicationController extends BaseController
{
    private const HISTORY_PER_PAGE = 10;

    private StudentRepository $studentRepo;
    private CommunicationService $commService;
    private UserRepository $userRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!$this->auth->hasPermission('communications')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission d\'accéder aux communications.');
            $this->redirect('dashboard.php');
            return;
        }

        $db = Container::db();
        $this->studentRepo = new StudentRepository($db);
        $this->userRepo = new UserRepository($db);
        $commRepo = new CommunicationRepository($db);
        $quotaService = new MailQuotaService(new MailDailyUsageRepository($db), $commRepo);
        $emailService = new EmailService(
            Runtime::env('MAIL_USER'),
            Runtime::env('MAIL_PASS'),
            'noreply@aeesgs.org',
            'AEESGS',
            $this->view
        );
        $this->commService = new CommunicationService(
            $commRepo,
            $quotaService,
            new SettingRepository($db),
            $emailService,
            $this->view
        );
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $communicationId = (int) ($_GET['campaign'] ?? $_GET['communication'] ?? 0);
        $activeCommunication = $communicationId > 0
            ? $this->commService->getCommunication($communicationId, CommunicationType::BULK)
            : null;

        $historyTotal = $this->commService->countHistory(CommunicationType::BULK);
        $historyTotalPages = max(1, (int) ceil($historyTotal / self::HISTORY_PER_PAGE));
        $historyPage = max(1, min($historyTotalPages, (int) ($_GET['page'] ?? 1)));
        $history = $this->commService->getRecentHistoryWithFailures(
            CommunicationType::BULK,
            self::HISTORY_PER_PAGE,
            ($historyPage - 1) * self::HISTORY_PER_PAGE
        );

        $defaultCount = $this->commService->countAudience(['kyc_status' => 'exclude_rejected']);
        $currentUser = $this->userRepo->findById($this->session->userId());
        $failureCampaignId = (int) ($_GET['failures'] ?? 0);
        $failureCampaign = $failureCampaignId > 0
            ? $this->commService->getCommunication($failureCampaignId, CommunicationType::BULK)
            : null;
        $failurePageSize = 25;
        $failurePages = max(1, (int) ceil(($failureCampaign['failed_count'] ?? 0) / $failurePageSize));
        $failurePage = max(1, min($failurePages, (int) ($_GET['failure_page'] ?? 1)));

        $this->render('admin/pages/communications.html.twig', [
            'institutions' => $this->studentRepo->getDistinctInstitutions(),
            'stats' => $this->commService->getDashboardStats(),
            'default_recipient_count' => $defaultCount,
            'reply_to_email' => $this->commService->getReplyToAddress(),
            'active_communication' => $activeCommunication,
            'staff_email' => $currentUser?->getEmail() ?? '',
            'history' => $history,
            'history_page' => $historyPage,
            'history_total_pages' => $historyTotalPages,
            'failure_campaign' => $failureCampaign,
            'failure_page' => $failurePage,
            'failure_pages' => $failurePages,
            'failure_details' => $failureCampaign === null ? [] : $this->commService->getFailedRecipients(
                $failureCampaignId, $failurePageSize, ($failurePage - 1) * $failurePageSize
            ),
            'status_labels' => CommunicationStatus::labelsMap(),
            'status_badges' => CommunicationStatus::badgesMap(),
        ]);
    }

    // ─── count (AJAX) ──────────────────────────────────────────────────────────

    public function count(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $count = $this->commService->countAudience([
            'status' => $_GET['status'] ?? '',
            'institution' => $_GET['institution'] ?? '',
            'consent' => $_GET['consent'] ?? '',
            'kyc_status' => $_GET['kyc_status'] ?? 'exclude_rejected',
        ]);
        echo json_encode(['count' => $count], JSON_THROW_ON_ERROR);
        exit();
    }

    // ─── send (POST actions: send, self_test, resume, retry_failed, cancel) ────

    public function send(): void
    {
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';

        if (!$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'La session a expiré. Veuillez recharger la page.'], JSON_THROW_ON_ERROR);
                exit();
            }
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('communications.php');
            return;
        }

        $action = trim((string) ($_POST['action'] ?? 'send'));

        if ($action === 'self_test') {
            $this->handleSelfTest($wantsJson);
            return;
        }
        if ($action === 'resume') {
            $this->handleResume();
            return;
        }
        if ($action === 'retry_failed') {
            $this->handleRetryFailed();
            return;
        }
        if ($action === 'cancel') {
            $this->handleCancel();
            return;
        }

        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        if ($subject === '' || $body === '') {
            $this->flash->set('error', 'Le sujet et le corps du message sont requis.');
            $this->redirect('communications.php');
            return;
        }
        if (mb_strlen($subject) > 255) {
            $this->flash->set('error', 'Le sujet ne peut pas dépasser 255 caractères.');
            $this->redirect('communications.php');
            return;
        }

        $filters = [
            'status' => $_POST['status'] ?? '',
            'institution' => $_POST['institution'] ?? '',
            'consent' => $_POST['consent'] ?? '',
            'kyc_status' => $_POST['kyc_status'] ?? 'exclude_rejected',
        ];

        $confirmedCount = $_POST['confirmed_recipient_count'] ?? '';
        if ($confirmedCount !== '' && (!is_string($confirmedCount)
            || filter_var($confirmedCount, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)) {
            $this->flash->set('warning', 'Veuillez vérifier et confirmer le nombre de destinataires.');
            $this->redirect('communications.php');
            return;
        }
        $communication = $this->commService->queueBulkCommunication(
            $subject,
            $body,
            $filters,
            $this->session->userId() ?: null,
            $confirmedCount === '' ? null : (int) $confirmedCount
        );

        if ($communication['status'] === 'AUDIENCE_CHANGED') {
            $this->flash->set('warning', 'Le nombre de destinataires a changé. Veuillez le vérifier et confirmer à nouveau.');
            $this->redirect('communications.php');
            return;
        }

        $total = (int) ($communication['total_recipients'] ?? 0);
        if ($total === 0) {
            $this->flash->set('warning', 'Aucun adhérent éligible avec une adresse email valide ne correspond à ces filtres (0 email(s)).');
            $this->redirect('communications.php');
            return;
        }

        $this->flash->set(
            'success',
            sprintf('Communication enregistrée : envoi de %d email(s) en cours.', $total)
        );
        $this->redirect('communications.php?campaign=' . (int) $communication['id']);
    }

    // ─── dispatch (AJAX POST chunk) ────────────────────────────────────────────

    public function dispatch(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->csrf->verify($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Session expirée. Veuillez recharger la page.'], JSON_THROW_ON_ERROR);
            exit();
        }

        $communicationId = (int) ($_POST['communication_id'] ?? 0);
        if ($communicationId <= 0
            || $this->commService->getCommunication($communicationId, CommunicationType::BULK) === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Communication introuvable.'], JSON_THROW_ON_ERROR);
            exit();
        }

        session_write_close();
        $result = $this->commService->dispatchChunk($communicationId, CommunicationService::DEFAULT_CHUNK_SIZE);
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ─── private action handlers ───────────────────────────────────────────────

    private function handleSelfTest(bool $wantsJson): void
    {
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        if ($subject === '' || $body === '') {
            $msg = 'Veuillez renseigner un sujet et un message avant d’envoyer un test.';
            if ($wantsJson) {
                header('Content-Type: application/json; charset=UTF-8');
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => $msg], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                exit();
            }
            $this->flash->set('error', $msg);
            $this->redirect('communications.php');
            return;
        }

        $user = $this->userRepo->findById($this->session->userId());
        $email = $user?->getEmail() ?? '';
        $firstName = $user?->getFirstName() ?: (string) $this->session->get('first_name', 'Administrateur');
        $lastName = $user?->getLastName() ?: (string) $this->session->get('last_name', '');

        $result = $this->commService->sendSelfTest($email, $firstName, $lastName, $subject, $body);

        if ($wantsJson) {
            header('Content-Type: application/json; charset=UTF-8');
            if (!$result['ok']) {
                http_response_code(422);
            }
            echo json_encode([
                'ok' => $result['ok'],
                'email' => $email,
                'message' => $result['ok']
                    ? 'Email de test envoyé à ' . $email . '.'
                    : ($result['error'] ?: 'Impossible d’envoyer l’email de test.'),
                'error' => $result['error'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            exit();
        }

        if ($result['ok']) {
            $this->flash->set('success', 'Email de test envoyé à ' . $email . '.');
        } else {
            $this->flash->set('error', $result['error'] ?: 'Impossible d’envoyer l’email de test.');
        }
        $this->redirect('communications.php');
    }

    private function handleResume(): void
    {
        $id = (int) ($_POST['communication_id'] ?? 0);
        if ($id <= 0 || $this->commService->getCommunication($id, CommunicationType::BULK) === null) {
            $this->flash->set('error', 'Communication introuvable.');
            $this->redirect('communications.php');
            return;
        }

        $this->flash->set('info', sprintf('Reprise de la communication #%d en cours.', $id));
        $this->redirect('communications.php?communication=' . $id);
    }

    private function handleRetryFailed(): void
    {
        $id = (int) ($_POST['communication_id'] ?? 0);
        if ($id <= 0 || $this->commService->getCommunication($id, CommunicationType::BULK) === null) {
            $this->flash->set('error', 'Communication introuvable.');
            $this->redirect('communications.php');
            return;
        }

        $requeued = $this->commService->retryFailed($id);
        if ($requeued === 0) {
            $this->flash->set('info', 'Aucun destinataire en échec ne peut être relancé pour le moment.');
            $this->redirect('communications.php?communication=' . $id);
            return;
        }

        $this->flash->set('success', sprintf('%d destinataire(s) en échec remis en file d’envoi.', $requeued));
        $this->redirect('communications.php?communication=' . $id);
    }

    private function handleCancel(): void
    {
        $id = (int) ($_POST['communication_id'] ?? 0);
        if ($id <= 0 || $this->commService->getCommunication($id, CommunicationType::BULK) === null) {
            $this->flash->set('error', 'Communication introuvable.');
            $this->redirect('communications.php');
            return;
        }

        $cancelled = $this->commService->cancelRemaining($id);
        $this->flash->set('warning', sprintf('%d destinataire(s) restant(s) annulé(s).', $cancelled));
        $this->redirect('communications.php?communication=' . $id);
    }
}
