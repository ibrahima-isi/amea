<?php
/**
 * Document reconciliation page (admin only).
 * Detects and repairs broken upload-path links caused by folder restoration.
 */

require_once 'config/session.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit();
}

require_once 'config/database.php';
require_once 'functions/utility-functions.php';
require_once 'functions/document-reconcile.php';

$prenom = $_SESSION['prenom'];
$nom    = $_SESSION['nom'];
$root   = __DIR__;

// Directories to search (relative to project root)
$searchDirs = ['uploads/', 'uploads/students/', 'uploads/students/cvs/'];

// ─── POST: fix a batch of auto-detected path issues ───────────────────────────
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Session expirée. Veuillez réessayer.');
        header('Location: reconcile-documents.php'); exit();
    }

    $action = $_POST['action'] ?? '';

    // ── Batch-fix all auto-detectable path mismatches ────────────────────────
    if ($action === 'fix_paths') {
        $fixed  = 0;
        $errors = 0;

        $students = $conn->query(
            "SELECT id_personne, identite, cv_path FROM personnes"
        )->fetchAll(PDO::FETCH_ASSOC);

        $updIdentite = $conn->prepare(
            'UPDATE personnes SET identite = ? WHERE id_personne = ?'
        );
        $updCv = $conn->prepare(
            'UPDATE personnes SET cv_path = ? WHERE id_personne = ?'
        );

        foreach ($students as $s) {
            // Identite
            $identiteInfo = classifyDocument($s['identite'], $root, $searchDirs);
            if ($identiteInfo['status'] === 'fixable') {
                try {
                    $updIdentite->execute([$identiteInfo['found_at'], $s['id_personne']]);
                    $fixed++;
                } catch (PDOException $e) {
                    logError('reconcile identite fix failed', $e);
                    $errors++;
                }
            }

            // CV
            $cvInfo = classifyDocument($s['cv_path'], $root, $searchDirs);
            if ($cvInfo['status'] === 'fixable') {
                try {
                    $updCv->execute([$cvInfo['found_at'], $s['id_personne']]);
                    $fixed++;
                } catch (PDOException $e) {
                    logError('reconcile cv fix failed', $e);
                    $errors++;
                }
            }
        }

        $result = [
            'type'    => $errors === 0 ? 'success' : 'warning',
            'message' => "$fixed chemin(s) corrigé(s)." .
                         ($errors ? " $errors erreur(s) rencontrée(s)." : ''),
        ];
    }

    // ── Assign an orphaned file to a student's identite or cv_path ───────────
    if ($action === 'assign_file') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $filePath  = $_POST['file_path'] ?? '';
        $field     = $_POST['field'] ?? '';

        // Validate: file must be inside uploads/ and actually exist
        $absFile    = realpath($root . '/' . ltrim($filePath, '/'));
        $absUploads = realpath($root . '/uploads');

        // Path traversal guard: resolved file must be strictly inside uploads/
        $safePrefix = $absUploads !== false ? ($absUploads . DIRECTORY_SEPARATOR) : '';

        if ($studentId <= 0
            || !in_array($field, ['identite', 'cv_path'], true)
            || $absFile === false
            || $absUploads === false
            || strpos($absFile, $safePrefix) !== 0
            || !is_file($absFile)
        ) {
            $result = ['type' => 'error', 'message' => 'Paramètres invalides.'];
        } else {
            // Use separate pre-prepared statements per field — never interpolate $field into SQL
            try {
                if ($field === 'identite') {
                    $conn->prepare('UPDATE personnes SET identite = ? WHERE id_personne = ?')
                         ->execute([$filePath, $studentId]);
                } else {
                    $conn->prepare('UPDATE personnes SET cv_path = ? WHERE id_personne = ?')
                         ->execute([$filePath, $studentId]);
                }
                $result = ['type' => 'success', 'message' => 'Document assigné avec succès.'];
            } catch (PDOException $e) {
                logError('reconcile assign failed', $e);
                $result = ['type' => 'error', 'message' => 'Erreur lors de l\'assignation.'];
            }
        }
    }
}

// ─── Analyse current state ────────────────────────────────────────────────────
$students = $conn->query(
    "SELECT id_personne, nom, prenom, identite, cv_path, date_enregistrement FROM personnes ORDER BY nom, prenom"
)->fetchAll(PDO::FETCH_ASSOC);

$allDbPaths  = array_merge(
    array_column($students, 'identite'),
    array_column($students, 'cv_path')
);
$allFiles    = scanUploadFiles($root, $searchDirs);
$orphaned    = findOrphanedFiles($allFiles, $allDbPaths);

// Categorise each student's documents
$countOk       = 0;
$countFixable  = 0;
$countMissing  = 0;
$countNull     = 0;

$fixableRows = '';
$missingRows = '';

foreach ($students as $s) {
    $id = (int)$s['id_personne'];
    $name = htmlspecialchars($s['prenom'] . ' ' . $s['nom'], ENT_QUOTES, 'UTF-8');

    foreach (['identite' => 'Identité', 'cv_path' => 'CV'] as $field => $label) {
        $info = classifyDocument($s[$field], $root, $searchDirs);

        switch ($info['status']) {
            case 'ok':      $countOk++;      break;
            case 'null':    $countNull++;     break;

            case 'fixable':
                $countFixable++;
                $fixableRows .= '<tr>'
                    . '<td>' . $name . '</td>'
                    . '<td><span class="badge bg-secondary">' . $label . '</span></td>'
                    . '<td class="text-danger text-truncate" style="max-width:200px;" title="' . htmlspecialchars($s[$field], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($s[$field], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="text-success text-truncate" style="max-width:200px;" title="' . htmlspecialchars($info['found_at'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($info['found_at'], ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
                break;

            case 'missing':
                $countMissing++;
                $missingRows .= '<tr>'
                    . '<td>' . $name . '</td>'
                    . '<td><span class="badge bg-secondary">' . $label . '</span></td>'
                    . '<td class="text-danger">' . htmlspecialchars($s[$field]) . '</td>'
                    . '</tr>';
                break;
        }
    }
}

if (empty($fixableRows)) {
    $fixableRows = '<tr><td colspan="4" class="text-center text-muted fst-italic py-3">Aucun chemin corrigeable détecté.</td></tr>';
}
if (empty($missingRows)) {
    $missingRows = '<tr><td colspan="3" class="text-center text-muted fst-italic py-3">Aucun document manquant.</td></tr>';
}

// ─── Build orphaned files table ───────────────────────────────────────────────
// For each orphaned file we extract the upload timestamp encoded in its uniqid
// filename (first 8 hex chars = Unix seconds) and suggest students whose
// date_enregistrement falls within ±14 days of that upload.

$orphanRows = '';
foreach ($orphaned as $file) {
    $basename = basename($file);
    $ext      = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    $isImage  = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
    $isPdf    = ($ext === 'pdf');
    $fieldSuggestion = $isPdf ? 'cv_path' : 'identite';
    $fileUrl  = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');

    // Decode upload timestamp from filename prefix (uniqid format: 8 hex chars = seconds)
    $uploadTs   = (strlen($basename) >= 8) ? hexdec(substr($basename, 0, 8)) : 0;
    $uploadDate = ($uploadTs > 0) ? date('d/m/Y', $uploadTs) : '';

    // Split students into "suggested" (registered ±14 days) vs rest
    $suggested = [];
    $others    = [];
    foreach ($students as $s) {
        if ($uploadTs > 0 && !empty($s['date_enregistrement'])) {
            $diff = abs(strtotime($s['date_enregistrement']) - $uploadTs);
            if ($diff <= 5 * 60) {
                $suggested[] = $s;
                continue;
            }
        }
        $others[] = $s;
    }

    // Build per-file select — suggested group first
    $opts = '<option value="">— Choisir —</option>';
    if (!empty($suggested)) {
        $opts .= '<optgroup label="Suggestions (±5 min)">';
        foreach ($suggested as $s) {
            $opts .= '<option value="' . (int)$s['id_personne'] . '">'
                . htmlspecialchars($s['prenom'] . ' ' . $s['nom'], ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        $opts .= '</optgroup><optgroup label="Tous les étudiants">';
    }
    foreach ($others as $s) {
        $opts .= '<option value="' . (int)$s['id_personne'] . '">'
            . htmlspecialchars($s['prenom'] . ' ' . $s['nom'], ENT_QUOTES, 'UTF-8')
            . '</option>';
    }
    if (!empty($suggested)) {
        $opts .= '</optgroup>';
    }

    // Preview cell
    if ($isImage) {
        $previewCell = '<img src="' . $fileUrl . '" alt="" style="height:56px;width:56px;object-fit:cover;border-radius:4px;cursor:pointer;" '
            . 'data-bs-toggle="modal" data-bs-target="#previewModal" '
            . 'data-preview-src="' . $fileUrl . '" '
            . 'title="Cliquer pour agrandir">';
    } elseif ($isPdf) {
        $previewCell = '<a href="' . $fileUrl . '" target="_blank" class="btn btn-sm btn-outline-danger">'
            . '<i class="fas fa-file-pdf me-1"></i>Ouvrir</a>';
    } else {
        $previewCell = '<span class="text-muted small">—</span>';
    }

    $dateLabel = $uploadDate
        ? '<br><span class="text-muted small">Déposé le ' . $uploadDate . '</span>'
        : '';

    $orphanRows .= '<tr>'
        . '<td style="width:70px;">' . $previewCell . '</td>'
        . '<td><span class="font-monospace small">' . htmlspecialchars($basename, ENT_QUOTES, 'UTF-8') . '</span>' . $dateLabel . '</td>'
        . '<td>'
        . '<form method="POST" action="reconcile-documents.php" class="d-flex flex-wrap gap-2 align-items-center">'
        . '<input type="hidden" name="csrf_token" value="{{csrf_token}}">'
        . '<input type="hidden" name="action" value="assign_file">'
        . '<input type="hidden" name="file_path" value="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '">'
        . '<select name="student_id" class="form-select form-select-sm" style="max-width:240px;" required>'
        . $opts
        . '</select>'
        . '<select name="field" class="form-select form-select-sm" style="max-width:120px;">'
        . '<option value="identite"' . ($fieldSuggestion === 'identite' ? ' selected' : '') . '>Identité</option>'
        . '<option value="cv_path"'  . ($fieldSuggestion === 'cv_path'  ? ' selected' : '') . '>CV</option>'
        . '</select>'
        . '<button type="submit" class="btn btn-sm btn-success"><i class="fas fa-link me-1"></i>Assigner</button>'
        . '</form>'
        . '</td>'
        . '</tr>';
}
if (empty($orphanRows)) {
    $orphanRows = '<tr><td colspan="3" class="text-center text-muted fst-italic py-3">Aucun fichier orphelin détecté.</td></tr>';
}

// ─── Render ───────────────────────────────────────────────────────────────────
$layoutPath  = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/reconcile-documents.html';

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

$flash      = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

$resultHtml = '';
if ($result) {
    $cls = $result['type'] === 'success' ? 'success' : ($result['type'] === 'warning' ? 'warning' : 'danger');
    $ico = $result['type'] === 'success' ? 'check-circle' : 'exclamation-triangle';
    $resultHtml = '<div class="alert alert-' . $cls . ' d-flex align-items-center gap-2"><i class="fas fa-' . $ico . '"></i>'
        . htmlspecialchars($result['message']) . '</div>';
}

$csrfToken = generateCsrfToken();

// Inject CSRF into orphan rows now so {{csrf_token}} inside them is already resolved
// before the main strtr() runs — avoids a nested str_replace() call.
$orphanRowsFinal = str_replace('{{csrf_token}}', htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'), $orphanRows);

$contentTpl  = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{result_html}}'           => $resultHtml,
    '{{count_ok}}'              => $countOk,
    '{{count_fixable}}'         => $countFixable,
    '{{count_missing}}'         => $countMissing,
    '{{count_null}}'            => $countNull,
    '{{count_orphan}}'          => count($orphaned),
    '{{fixable_rows}}'          => $fixableRows,
    '{{missing_rows}}'          => $missingRows,
    '{{orphan_rows}}'           => $orphanRowsFinal,
    '{{csrf_token}}'            => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
    '{{fix_btn_disabled}}'      => $countFixable === 0 ? 'disabled' : '',
    '{{count_fixable_nonzero}}' => '', // placeholder consumed by strtr, no extra UI needed
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}'                  => 'AEESGS — Réconciliation des documents',
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
