<?php
/**
 * Annual grade-level upgrade page (admin only).
 * Lists ALL students with a per-student level selector.
 * Admin picks any target level; action = auto_upgrade or send_emails.
 */

require_once 'config/session.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit();
}

require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!hasPermission('upgrade')) {
    setFlashMessage('error', 'Accès refusé : vous n\'avez pas la permission de gérer la mise à niveau des membres.');
    header('Location: dashboard.php'); exit();
}

require_once 'functions/email-service.php';

$prenom = $_SESSION['first_name'] ?? '';
$nom    = $_SESSION['last_name'] ?? '';

// ─── Ensure table exists (runs once, no-op if already there) ─────────────
$conn->exec("CREATE TABLE IF NOT EXISTS pending_level_upgrades (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    old_level     VARCHAR(100) NOT NULL,
    new_level     VARCHAR(100) NOT NULL,
    token         VARCHAR(100) NOT NULL UNIQUE,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME NOT NULL,
    confirmed_at  DATETIME NULL,
    INDEX idx_token    (token),
    INDEX idx_personne (student_id),
    CONSTRAINT fk_plu_personne FOREIGN KEY (student_id)
        REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── Natural next-level suggestions (pre-selects dropdown, not enforced) ─
const GRADE_NEXT = [
    'Seconde'                                         => 'Première',
    'Première'                                        => 'Terminale',
    'Licence 1 (L1)'                                  => 'Licence 2 (L2)',
    'Licence 2 (L2)'                                  => 'Licence 3 (L3)',
    'Licence 3 (L3)'                                  => 'Master 1 (M1)',
    'Master 1 (M1)'                                   => 'Master 2 (M2)',
    'Master 2 (M2)'                                   => 'Doctorat',
    'BTS 1 (Brevet de Technicien Supérieur)'          => 'BTS 2 (Brevet de Technicien Supérieur)',
];

// ─── All available levels (from DB, ordered) ─────────────────────────────
$allLevels = $conn->query("SELECT name FROM study_levels ORDER BY id")
                  ->fetchAll(PDO::FETCH_COLUMN);

// ─── Eligible students ────────────────────────────────────────────────────
// Rules: registered >= 1 year ago, not Diplômé, and no upgrade confirmed
// in the last 12 months (covers both auto-upgrades and email-confirmed ones).
$stmtAll = $conn->query(
    "SELECT id, last_name, first_name, email, study_level, status
     FROM students
     WHERE status NOT IN ('GRADUATE', 'DIPLOME', 'Diplômé')
       AND registration_date <= DATE_SUB(NOW(), INTERVAL 1 YEAR)
       AND NOT EXISTS (
           SELECT 1 FROM pending_level_upgrades plu
           WHERE plu.student_id = students.id
             AND plu.confirmed_at IS NOT NULL
             AND plu.confirmed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
       )
     ORDER BY study_level, last_name, first_name"
);
$allStudents = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

$result = null;

// ─── POST handlers ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Session expirée. Veuillez réessayer.');
        header('Location: upgrade-levels.php'); exit();
    }

    $action   = $_POST['action'] ?? '';
    $upgrades = $_POST['upgrades'] ?? []; // [ id => ['next' => '...', 'selected' => '1'] ]

    // Build list of validated upgrade targets (selected + level changed + level valid)
    $targets = [];
    foreach ($upgrades as $idStr => $data) {
        $id       = (int)$idStr;
        $next     = $data['next'] ?? '';
        $selected = !empty($data['selected']);
        if (!$selected || $id <= 0 || !in_array($next, $allLevels, true)) {
            continue;
        }
        // Find current level from loaded list
        foreach ($allStudents as $s) {
            if ((int)$s['id'] === $id) {
                if ($s['study_level'] !== $next) { // skip no-change
                    $targets[] = [
                        'id'             => $id,
                        'old_level'      => $s['study_level'],
                        'new_level'      => $next,
                        'email'          => $s['email'],
                        'first_name'     => $s['first_name'],
                        'last_name'      => $s['last_name'],
                    ];
                }
                break;
            }
        }
    }

    // ── AUTO-UPGRADE ──────────────────────────────────────────────────────
    if ($action === 'auto_upgrade') {
        $upgraded = 0;
        $conn->beginTransaction();
        try {
            $updateStmt = $conn->prepare(
                'UPDATE students SET study_level = ? WHERE id = ? AND study_level = ?'
            );
            $trackStmt = $conn->prepare(
                'INSERT IGNORE INTO pending_level_upgrades
                 (student_id, old_level, new_level, token, expires_at, confirmed_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            );
            foreach ($targets as $t) {
                $updateStmt->execute([$t['new_level'], $t['id'], $t['old_level']]);
                if ($updateStmt->rowCount() > 0) {
                    $upgraded++;
                    $trackStmt->execute([
                        $t['id'], $t['old_level'], $t['new_level'],
                        'auto_' . $t['id'] . '_' . bin2hex(random_bytes(8)),
                    ]);
                }
            }
            $conn->commit();
            $result = ['type' => 'success', 'message' => "$upgraded étudiant(s) mis à niveau avec succès."];
        } catch (Exception $e) {
            $conn->rollBack();
            logError('auto_upgrade failed', $e);
            $result = ['type' => 'error', 'message' => 'Une erreur est survenue. Aucune modification effectuée.'];
        }
        // Refresh list (re-apply eligibility filter)
        $allStudents = $conn->query(
            "SELECT id, last_name, first_name, email, study_level, status
             FROM students
             WHERE status NOT IN ('GRADUATE', 'DIPLOME', 'Diplômé')
               AND registration_date <= DATE_SUB(NOW(), INTERVAL 1 YEAR)
               AND NOT EXISTS (
                   SELECT 1 FROM pending_level_upgrades plu
                   WHERE plu.student_id = students.id
                     AND plu.confirmed_at IS NOT NULL
                     AND plu.confirmed_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
               )
             ORDER BY study_level, last_name, first_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── SEND CONFIRMATION EMAILS ──────────────────────────────────────────
    if ($action === 'send_emails') {
        $sent    = 0;
        $skipped = 0;
        $appUrl  = rtrim(env('APP_URL') ?: 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');

        // Delete expired / unconfirmed pending upgrades before creating new ones
        $conn->exec("DELETE FROM pending_level_upgrades WHERE expires_at < NOW() AND confirmed_at IS NULL");

        $insertStmt = $conn->prepare(
            'INSERT IGNORE INTO pending_level_upgrades
             (student_id, old_level, new_level, token, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))'
        );

        foreach ($targets as $t) {
            if (empty($t['email'])) { $skipped++; continue; }

            // Check if a pending (non-expired, non-confirmed) upgrade already exists
            $check = $conn->prepare(
                'SELECT id FROM pending_level_upgrades
                 WHERE student_id = ? AND confirmed_at IS NULL AND expires_at > NOW()'
            );
            $check->execute([$t['id']]);
            if ($check->fetch()) { $skipped++; continue; }

            $token = bin2hex(random_bytes(32));
            $insertStmt->execute([$t['id'], $t['old_level'], $t['new_level'], $token]);

            $confirmLink = $appUrl . '/confirm-upgrade.php?token=' . $token;
            $body = renderEmailTemplate(
                __DIR__ . '/templates/emails/grade-upgrade-email.html',
                [
                    'prenom'        => htmlspecialchars($t['first_name']),
                    'nom'           => htmlspecialchars($t['last_name']),
                    'ancien_niveau' => htmlspecialchars($t['old_level']),
                    'nouveau_niveau'=> htmlspecialchars($t['new_level']),
                    'confirm_link'  => $confirmLink,
                    'expires_in'    => '7 jours',
                ]
            );

            if (sendMail($t['email'], 'AEESGS — Confirmation de votre nouveau niveau d\'études', $body)) {
                $sent++;
            } else {
                $conn->prepare('DELETE FROM pending_level_upgrades WHERE token = ?')->execute([$token]);
                $skipped++;
            }
        }

        $result = ['type' => 'success',
            'message' => "$sent e-mail(s) envoyé(s)." . ($skipped ? " $skipped ignoré(s) (déjà en attente ou email absent)." : '')];
    }
}

// ─── Pending confirmation stats ───────────────────────────────────────────
$pendingCount = (int)$conn->query(
    "SELECT COUNT(*) FROM pending_level_upgrades WHERE confirmed_at IS NULL AND expires_at > NOW()"
)->fetchColumn();
$confirmedCount = (int)$conn->query(
    "SELECT COUNT(*) FROM pending_level_upgrades WHERE confirmed_at IS NOT NULL"
)->fetchColumn();

// ─── Render ───────────────────────────────────────────────────────────────
$layoutPath  = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/upgrade-levels.html';

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

$flash      = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

// Build the levels options HTML (reusable snippet)
$levelOptionsHtml = '';
foreach ($allLevels as $lvl) {
    $levelOptionsHtml .= '<option value="' . htmlspecialchars($lvl) . '">' . htmlspecialchars($lvl) . '</option>';
}

// Build student table rows
$rows = '';
$statutLabels = ['ELEVE' => 'Élève', 'ETUDIANT' => 'Étudiant', 'STAGIAIRE' => 'Stagiaire'];
foreach ($allStudents as $s) {
    $suggestion = GRADE_NEXT[$s['study_level']] ?? $s['study_level'];
    $id = (int)$s['id'];
    $statutLabel = $statutLabels[$s['status']] ?? htmlspecialchars($s['status']);

    // Build select options with the suggestion pre-selected
    $options = '';
    foreach ($allLevels as $lvl) {
        $selected = ($lvl === $suggestion) ? ' selected' : '';
        $options .= '<option value="' . htmlspecialchars($lvl) . '"' . $selected . '>' . htmlspecialchars($lvl) . '</option>';
    }

    $rows .= '<tr>'
        . '<td><input type="checkbox" name="upgrades[' . $id . '][selected]" value="1" class="form-check-input student-checkbox"></td>'
        . '<td>' . htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) . '</td>'
        . '<td>' . htmlspecialchars($s['study_level']) . '</td>'
        . '<td><span class="badge bg-secondary fw-normal">' . $statutLabel . '</span></td>'
        . '<td>'
        . '<select name="upgrades[' . $id . '][next]" class="form-select form-select-sm level-select" style="min-width:200px;">'
        . $options
        . '</select>'
        . '</td>'
        . '<td><a href="mailto:' . htmlspecialchars($s['email']) . '" class="text-muted small">' . htmlspecialchars($s['email'] ?: '—') . '</a></td>'
        . '</tr>';
}
if (empty($rows)) {
    $rows = '<tr><td colspan="6" class="text-center text-muted fst-italic py-4">Aucun étudiant enregistré.</td></tr>';
}

$resultHtml = '';
if ($result) {
    $cls = $result['type'] === 'success' ? 'success' : 'danger';
    $ico = $result['type'] === 'success' ? 'check-circle' : 'times-circle';
    $resultHtml = '<div class="alert alert-' . $cls . ' d-flex align-items-center gap-2"><i class="fas fa-' . $ico . '"></i>' . htmlspecialchars($result['message']) . '</div>';
}

$contentTpl  = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{result_html}}'     => $resultHtml,
    '{{student_rows}}'    => $rows,
    '{{total}}'           => count($allStudents),
    '{{pending_count}}'   => $pendingCount,
    '{{confirmed_count}}' => $confirmedCount,
    '{{csrf_token}}'      => generateCsrfToken(),
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}'                  => 'AEESGS — Mise à niveau annuelle',
    '{{sidebar}}'                => $sidebarHtml,
    '{{flash_json}}'             => $flash_json,
    '{{validation_errors_json}}' => '',
    '{{admin_topbar}}'           => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}'                => $contentHtml,
    '{{admin_footer}}'           => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);