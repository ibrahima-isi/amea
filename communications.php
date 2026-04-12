<?php
/**
 * Admin communications page.
 * — CGU/Terms reminder campaign (tokenised per-student link)
 * — Bulk email to all or filtered students
 */

require_once 'config/session.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit();
}

require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!hasPermission('communications')) {
    setFlashMessage('error', 'Accès refusé : vous n\'avez pas la permission d\'accéder aux communications.');
    header('Location: dashboard.php'); exit();
}

require_once 'functions/email-service.php';

$prenom = $_SESSION['prenom'];
$nom    = $_SESSION['nom'];

// ─── Self-healing schema ───────────────────────────────────────────────────────
try { $conn->exec("ALTER TABLE personnes ADD COLUMN cgu_token VARCHAR(64) NULL"); }             catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE personnes ADD COLUMN cgu_reminder_sent_at DATETIME NULL"); }     catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE personnes ADD COLUMN consent_refused_at DATETIME NULL"); }       catch (PDOException $e) {}
try { $conn->exec("ALTER TABLE personnes ADD COLUMN deletion_requested_at DATETIME NULL"); }    catch (PDOException $e) {}
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS communications (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        subject         VARCHAR(255) NOT NULL,
        body            TEXT NOT NULL,
        recipient_count INT NOT NULL DEFAULT 0,
        sent_count      INT NOT NULL DEFAULT 0,
        sent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sent_by         INT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

// ─── Base URL (for CGU acceptance links) ──────────────────────────────────────
$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $proto . '://' . $_SERVER['HTTP_HOST'];

$result = null;

// ─── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Session expirée. Veuillez réessayer.');
        header('Location: communications.php'); exit();
    }

    $action = $_POST['action'] ?? '';

    // ── CGU reminder campaign ─────────────────────────────────────────────────
    if ($action === 'send_cgu') {
        // 'all' = include members who already accepted (CGU update); 'not_consented' = only those pending
        $scope = $_POST['cgu_scope'] ?? 'not_consented';

        if ($scope === 'selected') {
            $rawIds = array_filter(array_map('intval', (array)($_POST['selected_ids'] ?? [])), fn($id) => $id > 0);
            if (empty($rawIds)) {
                $result = ['type' => 'error', 'message' => 'Aucun membre sélectionné.', 'tab' => 'cgu'];
                goto render;
            }
            $placeholders = implode(',', array_fill(0, count($rawIds), '?'));
            $stmt = $conn->prepare(
                "SELECT id_personne, nom, prenom, email, consent_privacy
                 FROM personnes
                 WHERE id_personne IN ($placeholders)
                 AND email IS NOT NULL AND email != ''"
            );
            $stmt->execute(array_values($rawIds));
        } elseif ($scope === 'all') {
            $stmt = $conn->query(
                "SELECT id_personne, nom, prenom, email, consent_privacy
                 FROM personnes
                 WHERE email IS NOT NULL AND email != ''"
            );
        } else {
            $stmt = $conn->query(
                "SELECT id_personne, nom, prenom, email, consent_privacy
                 FROM personnes
                 WHERE (consent_privacy = 0 OR consent_privacy IS NULL)
                 AND email IS NOT NULL AND email != ''"
            );
        }
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0; $errors = 0;
        $tplPath = __DIR__ . '/templates/emails/cgu-reminder.html';
        $updToken = $conn->prepare(
            "UPDATE personnes SET cgu_token = ?, cgu_reminder_sent_at = NOW() WHERE id_personne = ?"
        );

        foreach ($targets as $t) {
            $token = bin2hex(random_bytes(32));
            $updToken->execute([$token, $t['id_personne']]);

            $acceptUrl = $baseUrl . '/accept-cgu.php?token=' . $token;
            $body = renderEmailTemplate($tplPath, [
                'prenom'     => htmlspecialchars($t['prenom'], ENT_QUOTES, 'UTF-8'),
                'nom'        => htmlspecialchars($t['nom'], ENT_QUOTES, 'UTF-8'),
                'accept_url' => $acceptUrl,
            ]);

            if (sendMail($t['email'], 'AEESGS – Acceptation des CGU et politique de confidentialité', $body)) {
                $sent++;
            } else {
                $errors++;
            }
        }

        $result = [
            'type'    => $errors === 0 ? 'success' : 'warning',
            'message' => "$sent email(s) CGU envoyé(s)." . ($errors ? " $errors erreur(s)." : ''),
            'tab'     => 'cgu',
        ];
    }

    // ── Bulk communication ────────────────────────────────────────────────────
    if ($action === 'send_bulk') {
        $subject     = trim($_POST['subject'] ?? '');
        $bodyContent = trim($_POST['body'] ?? '');
        $statutFilter  = $_POST['statut_filter'] ?? '';
        $consentFilter = $_POST['consent_filter'] ?? '';

        if (empty($subject) || empty($bodyContent)) {
            $result = ['type' => 'error', 'message' => 'Le sujet et le message sont obligatoires.', 'tab' => 'bulk'];
        } else {
            $where  = ["email IS NOT NULL AND email != ''"];
            $params = [];

            if (!empty($statutFilter)) {
                $where[]          = 'statut = :statut';
                $params[':statut'] = $statutFilter;
            }
            if ($consentFilter === 'consented') {
                $where[] = 'consent_privacy = 1';
            } elseif ($consentFilter === 'not_consented') {
                $where[] = '(consent_privacy = 0 OR consent_privacy IS NULL)';
            }

            $sql  = 'SELECT id_personne, nom, prenom, email FROM personnes WHERE ' . implode(' AND ', $where);
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sent = 0; $errors = 0;
            $tplPath = __DIR__ . '/templates/emails/bulk-communication.html';

            foreach ($targets as $t) {
                // Allow {{prenom}} and {{nom}} in the body as personalisation
                $personalised = strtr($bodyContent, [
                    '{{prenom}}' => htmlspecialchars($t['prenom'], ENT_QUOTES, 'UTF-8'),
                    '{{nom}}'    => htmlspecialchars($t['nom'], ENT_QUOTES, 'UTF-8'),
                ]);
                $body = renderEmailTemplate($tplPath, [
                    'subject' => htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'),
                    'prenom'  => htmlspecialchars($t['prenom'], ENT_QUOTES, 'UTF-8'),
                    'nom'     => htmlspecialchars($t['nom'], ENT_QUOTES, 'UTF-8'),
                    'content' => nl2br(htmlspecialchars($personalised, ENT_QUOTES, 'UTF-8')),
                ]);

                if (sendMail($t['email'], $subject, $body)) {
                    $sent++;
                } else {
                    $errors++;
                }
            }

            // Log campaign
            try {
                $conn->prepare(
                    "INSERT INTO communications (subject, body, recipient_count, sent_count, sent_by)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$subject, $bodyContent, count($targets), $sent, $_SESSION['user_id']]);
            } catch (PDOException $e) {
                logError('communications log insert failed', $e);
            }

            $result = [
                'type'    => $errors === 0 ? 'success' : 'warning',
                'message' => "$sent email(s) envoyé(s) sur " . count($targets) . " destinataire(s)."
                           . ($errors ? " $errors erreur(s)." : ''),
                'tab'     => 'bulk',
            ];
        }
    }
}

render:
// ─── Stats ─────────────────────────────────────────────────────────────────────
$cguStats = $conn->query(
    "SELECT
        COALESCE(SUM(consent_privacy = 1), 0)                          AS consented,
        COALESCE(SUM(consent_privacy = 0 OR consent_privacy IS NULL), 0) AS not_consented,
        COALESCE(SUM(cgu_reminder_sent_at IS NOT NULL), 0)             AS reminder_sent
     FROM personnes"
)->fetch(PDO::FETCH_ASSOC);

$totalStudents = (int)$conn->query("SELECT COUNT(*) FROM personnes")->fetchColumn();

// Students who haven't consented (for the CGU table)
$notConsentedList = $conn->query(
    "SELECT id_personne, nom, prenom, email, date_enregistrement, cgu_reminder_sent_at
     FROM personnes
     WHERE (consent_privacy = 0 OR consent_privacy IS NULL)
     ORDER BY nom, prenom"
)->fetchAll(PDO::FETCH_ASSOC);

// Pending deletion requests
$deletionRequests = $conn->query(
    "SELECT id_personne, nom, prenom, email, deletion_requested_at
     FROM personnes
     WHERE deletion_requested_at IS NOT NULL
     ORDER BY deletion_requested_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$countDeletionRequests = count($deletionRequests);

// All students for the selection checkbox list
$allStudentsForSelect = $conn->query(
    "SELECT id_personne, nom, prenom, email, consent_privacy
     FROM personnes ORDER BY nom, prenom"
)->fetchAll(PDO::FETCH_ASSOC);

// Recent communications history
$recentComms = $conn->query(
    "SELECT c.subject, c.sent_count, c.recipient_count, c.sent_at,
            CONCAT(u.prenom, ' ', u.nom) AS sent_by_name
     FROM communications c
     LEFT JOIN users u ON u.id_user = c.sent_by
     ORDER BY c.sent_at DESC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

// ─── Build table rows ─────────────────────────────────────────────────────────
$notConsentedRows = '';
foreach ($notConsentedList as $s) {
    $sentAt = $s['cgu_reminder_sent_at']
        ? '<span class="badge bg-warning text-dark">Rappel envoyé le ' . date('d/m/Y', strtotime($s['cgu_reminder_sent_at'])) . '</span>'
        : '<span class="badge bg-secondary">Pas encore contacté</span>';

    $notConsentedRows .= '<tr>'
        . '<td>' . htmlspecialchars($s['prenom'] . ' ' . $s['nom'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($s['email'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . date('d/m/Y', strtotime($s['date_enregistrement'])) . '</td>'
        . '<td>' . $sentAt . '</td>'
        . '</tr>';
}
if (empty($notConsentedRows)) {
    $notConsentedRows = '<tr><td colspan="4" class="text-center text-muted fst-italic py-3">Tous les membres ont accepté les CGU.</td></tr>';
}

// Student checkboxes for manual CGU selection
$studentCheckboxes = '';
foreach ($allStudentsForSelect as $s) {
    $consentBadge = $s['consent_privacy'] == 1
        ? '<span class="badge bg-success ms-2">CGU acceptées</span>'
        : '<span class="badge bg-warning text-dark ms-2">En attente</span>';
    $searchData = strtolower($s['prenom'] . ' ' . $s['nom'] . ' ' . ($s['email'] ?? ''));
    $studentCheckboxes .= '<div class="student-checkbox-item d-flex align-items-center gap-2 px-3 py-2 border-bottom"'
        . ' data-name="' . htmlspecialchars($searchData, ENT_QUOTES, 'UTF-8') . '">'
        . '<input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="selected_ids[]"'
        . ' value="' . (int)$s['id_personne'] . '" id="cgu_std_' . (int)$s['id_personne'] . '">'
        . '<label class="form-check-label flex-grow-1" for="cgu_std_' . (int)$s['id_personne'] . '">'
        . '<span class="fw-semibold">' . htmlspecialchars($s['prenom'] . ' ' . $s['nom'], ENT_QUOTES, 'UTF-8') . '</span>'
        . $consentBadge
        . '<br><small class="text-muted">' . htmlspecialchars($s['email'] ?? '—', ENT_QUOTES, 'UTF-8') . '</small>'
        . '</label>'
        . '</div>';
}

$deletionRows = '';
foreach ($deletionRequests as $d) {
    $deletionRows .= '<tr>'
        . '<td>' . htmlspecialchars($d['prenom'] . ' ' . $d['nom'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($d['email'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . date('d/m/Y H:i', strtotime($d['deletion_requested_at'])) . '</td>'
        . '<td>'
        . '<a href="student-details.php?id=' . (int)$d['id_personne'] . '" class="btn btn-sm btn-outline-primary me-1">'
        . '<i class="fas fa-eye"></i></a>'
        . '</td>'
        . '</tr>';
}
if (empty($deletionRows)) {
    $deletionRows = '<tr><td colspan="4" class="text-center text-muted fst-italic py-3">Aucune demande de suppression en attente.</td></tr>';
}

$historyRows = '';
foreach ($recentComms as $c) {
    $historyRows .= '<tr>'
        . '<td>' . htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . (int)$c['sent_count'] . ' / ' . (int)$c['recipient_count'] . '</td>'
        . '<td>' . date('d/m/Y H:i', strtotime($c['sent_at'])) . '</td>'
        . '<td>' . htmlspecialchars($c['sent_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>'
        . '</tr>';
}
if (empty($historyRows)) {
    $historyRows = '<tr><td colspan="4" class="text-center text-muted fst-italic py-3">Aucune communication envoyée.</td></tr>';
}

// ─── Render ───────────────────────────────────────────────────────────────────
$layoutPath  = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/communications.html';

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

$flash      = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

$resultHtml = '';
if ($result) {
    $cls = $result['type'] === 'success' ? 'success' : ($result['type'] === 'warning' ? 'warning' : 'danger');
    $ico = $result['type'] === 'success' ? 'check-circle' : 'exclamation-triangle';
    $resultHtml = '<div class="alert alert-' . $cls . ' d-flex align-items-center gap-2">'
        . '<i class="fas fa-' . $ico . '"></i>'
        . htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8')
        . '</div>';
}

$csrfToken = generateCsrfToken();

$contentTpl  = file_get_contents($contentPath);
$activeTab = ($result['tab'] ?? 'cgu') === 'bulk' ? 'bulk'
           : (($result['tab'] ?? 'cgu') === 'deletion' ? 'deletion' : 'cgu');

$contentHtml = strtr($contentTpl, [
    '{{result_html}}'              => $resultHtml,
    '{{active_cgu}}'               => $activeTab === 'cgu'      ? 'show active' : '',
    '{{active_bulk}}'              => $activeTab === 'bulk'     ? 'show active' : '',
    '{{active_deletion}}'          => $activeTab === 'deletion' ? 'show active' : '',
    '{{active_tab_cgu}}'           => $activeTab === 'cgu'      ? 'active' : '',
    '{{active_tab_bulk}}'          => $activeTab === 'bulk'     ? 'active' : '',
    '{{active_tab_deletion}}'      => $activeTab === 'deletion' ? 'active' : '',
    '{{count_consented}}'          => (int)$cguStats['consented'],
    '{{count_not_consented}}'      => (int)$cguStats['not_consented'],
    '{{count_reminder_sent}}'      => (int)$cguStats['reminder_sent'],
    '{{count_deletion_requests}}'  => $countDeletionRequests,
    '{{total_students}}'           => $totalStudents,
    '{{not_consented_rows}}'       => $notConsentedRows,
    '{{student_checkboxes}}'       => $studentCheckboxes,
    '{{deletion_rows}}'            => $deletionRows,
    '{{history_rows}}'             => $historyRows,
    '{{cgu_btn_disabled}}'         => $totalStudents === 0 ? 'disabled' : '',
    '{{deletion_badge}}'           => $countDeletionRequests > 0
        ? '<span class="badge bg-danger ms-2">' . $countDeletionRequests . '</span>'
        : '',
    '{{csrf_token}}'               => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}'                  => 'AEESGS — Communications',
    '{{sidebar}}'                => $sidebarHtml,
    '{{flash_json}}'             => $flash_json,
    '{{validation_errors_json}}' => '',
    '{{admin_topbar}}'           => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}'       => $contentHtml,
    '{{admin_footer}}'  => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
