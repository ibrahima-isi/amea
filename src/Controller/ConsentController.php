<?php

declare(strict_types=1);

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\Runtime;
use Amea\Model\CommunicationStatus;
use Amea\Model\CommunicationType;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\ConsentRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\CommunicationService;
use Amea\Service\ConsentService;
use Amea\Service\EmailService;
use Amea\Service\MailQuotaService;
use RuntimeException;

class ConsentController extends BaseController
{
    private const PENDING_PER_PAGE = 15;

    private ConsentService $consentService;
    private CommunicationService $commService;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!$this->auth->hasPermission('consent')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission d\'accéder au module Consentement & RGPD.');
            $this->redirect('dashboard.php');
            return;
        }

        $db = Container::db();
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
        $this->consentService = new ConsentService(
            new ConsentRepository($db),
            $this->commService,
            new StudentRepository($db)
        );
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $activeTab = (string) ($_GET['tab'] ?? 'cgu') === 'deletion' ? 'deletion' : 'cgu';

        $communicationId = (int) ($_GET['communication'] ?? 0);
        $activeCommunication = $communicationId > 0
            ? $this->commService->getCommunication($communicationId, CommunicationType::CGU_REMINDER)
            : null;

        $pendingQuery = trim((string) ($_GET['q'] ?? ''));
        $pendingPage = max(1, (int) ($_GET['page'] ?? 1));
        $pending = $this->consentService->getPendingConsentPage($pendingPage, self::PENDING_PER_PAGE, $pendingQuery);
        $pendingTotalPages = max(1, (int) ceil($pending['total'] / self::PENDING_PER_PAGE));
        if ($pendingPage > $pendingTotalPages) {
            $pendingPage = $pendingTotalPages;
            $pending = $this->consentService->getPendingConsentPage($pendingPage, self::PENDING_PER_PAGE, $pendingQuery);
        }

        $quotaStats = $this->commService->getDashboardStats();

        $this->render('admin/pages/consent.html.twig', [
            'stats' => $this->consentService->getStats(),
            'quota' => [
                'daily_sent' => $quotaStats['daily_sent'],
                'daily_limit' => $quotaStats['daily_limit'],
            ],
            'active_tab' => $activeTab,
            'active_communication' => $activeCommunication,
            'pending_members' => $pending['items'],
            'pending_total' => $pending['total'],
            'pending_page' => $pendingPage,
            'pending_total_pages' => $pendingTotalPages,
            'pending_query' => $pendingQuery,
            'deletion_requests' => $this->consentService->getDeletionRequests(),
            'public_origin_configured' => $this->consentService->isPublicOriginConfigured(),
            'can_trash_students' => $this->auth->hasPermission('students'),
            'status_labels' => CommunicationStatus::labelsMap(),
            'status_badges' => CommunicationStatus::badgesMap(),
        ]);
    }

    // ─── members (AJAX paginated search for pending-consent & manual selection) ─

    public function members(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $scope = (string) ($_GET['scope'] ?? 'all') === 'pending' ? 'pending' : 'all';
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $scope === 'pending' ? self::PENDING_PER_PAGE : 20;

        $result = $scope === 'pending'
            ? $this->consentService->getPendingConsentPage($page, $perPage, $query)
            : $this->consentService->searchSelectableMembers($query, $page, $perPage);

        echo json_encode([
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'total_pages' => max(1, (int) ceil($result['total'] / $perPage)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ─── handlePost (send_cgu, cancel, trash_student) ──────────────────────────

    public function handlePost(): void
    {
        if (!$this->csrf->verify((string) ($_POST['csrf_token'] ?? ''))) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('consent.php');
            return;
        }

        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'send_cgu') {
            $this->handleSendCgu();
            return;
        }

        if ($action === 'trash_student') {
            $this->handleTrashStudent();
            return;
        }

        if ($action === 'cancel') {
            $id = (int) ($_POST['communication_id'] ?? 0);
            if ($id <= 0 || $this->commService->getCommunication($id, CommunicationType::CGU_REMINDER) === null) {
                $this->flash->set('error', 'Rappel CGU introuvable.');
                $this->redirect('consent.php');
                return;
            }
            $cancelled = $this->commService->cancelRemaining($id);
            $this->flash->set('warning', sprintf('%d destinataire(s) restant(s) annulé(s).', $cancelled));
            $this->redirect('consent.php?communication=' . $id);
            return;
        }

        $this->redirect('consent.php');
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
            || $this->commService->getCommunication($communicationId, CommunicationType::CGU_REMINDER) === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Rappel CGU introuvable.'], JSON_THROW_ON_ERROR);
            exit();
        }

        session_write_close();
        $result = $this->consentService->dispatchChunk(
            $communicationId,
            CommunicationService::DEFAULT_CHUNK_SIZE
        );

        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ─── private helpers ───────────────────────────────────────────────────────

    private function handleSendCgu(): void
    {
        $scope = trim((string) ($_POST['cgu_scope'] ?? 'not_consented'));
        $rawIds = (array) ($_POST['selected_ids'] ?? []);
        $selectedIds = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($scope === 'selected' && $selectedIds === []) {
            $this->flash->set('error', 'Veuillez sélectionner au moins un adhérent.');
            $this->redirect('consent.php');
            return;
        }

        try {
            $reminder = $this->consentService->queueConsentReminder(
                $scope,
                $selectedIds,
                $this->session->userId() ?: null
            );
        } catch (RuntimeException $e) {
            $this->flash->set('error', $e->getMessage());
            $this->redirect('consent.php');
            return;
        }

        $total = (int) ($reminder['total_recipients'] ?? 0);
        if ($total === 0) {
            $this->flash->set('warning', 'Aucun adhérent éligible avec une adresse email valide n’a été trouvé pour ce rappel (0 email(s)).');
            $this->redirect('consent.php');
            return;
        }

        $this->flash->set(
            'success',
            sprintf('Rappel CGU enregistré : envoi de %d email(s) en cours.', $total)
        );
        $this->redirect('consent.php?communication=' . (int) $reminder['id']);
    }

    private function handleTrashStudent(): void
    {
        if (!$this->auth->hasPermission('students')) {
            $this->flash->set('error', 'Accès refusé : vous devez posséder la permission « Adhérents » pour déplacer un dossier vers la corbeille.');
            $this->redirect('consent.php?tab=deletion');
            return;
        }

        $studentId = (int) ($_POST['student_id'] ?? 0);
        if ($this->consentService->softDeleteMember($studentId, $this->session->userId())) {
            $this->flash->set('success', 'Dossier déplacé vers la corbeille. Il sera supprimé définitivement après la période de rétention.');
        } else {
            $this->flash->set('error', 'Impossible de trouver cet adhérent actif.');
        }
        $this->redirect('consent.php?tab=deletion');
    }
}
