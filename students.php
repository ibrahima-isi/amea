<?php

/**
 * Student list management page.
 * File: students.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit();
}

if (!hasPermission('students')) {
    setFlashMessage('error', 'Accès refusé : vous n\'avez pas la permission de consulter la liste des membres.');
    header('Location: dashboard.php'); exit();
}

$role = $_SESSION['role'];
$nom = $_SESSION['last_name'] ?? '';
$prenom = $_SESSION['first_name'] ?? '';
$csrfToken = generateCsrfToken();

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 10;
$offset = ($page - 1) * $perPage;

// Paramètres de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genderFilter = isset($_GET['gender']) ? $_GET['gender'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$institutionFilter = isset($_GET['institution']) ? $_GET['institution'] : '';
$nationalityFilter = isset($_GET['nationality']) ? $_GET['nationality'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Session expirée. Veuillez réessayer.');
        header("Location: students.php");
        exit();
    }
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $student_id_to_delete = (int)$_POST['id'];

        // First, get the photo path to delete the file
        $stmt = $conn->prepare("SELECT identity_document FROM students WHERE id = ?");
        $stmt->execute([$student_id_to_delete]);
        $identite_to_delete = $stmt->fetchColumn();

        // Delete the student from the database
        $deleteStmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $deleteStmt->execute([$student_id_to_delete]);

        // Delete the photo file if it exists
        safeUnlink($identite_to_delete);

        // Redirect to the same page to see the changes
        $queryParams = http_build_query([
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search,
            'gender' => $genderFilter,
            'status' => $statusFilter,
            'institution' => $institutionFilter,
            'nationality' => $nationalityFilter
        ]);
        header("Location: students.php?$queryParams");
        exit();
    }
}

// Construire la requête SQL avec les filtres
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(last_name LIKE :search_last_name OR first_name LIKE :search_first_name OR email LIKE :search_email OR phone LIKE :search_phone)";
    $searchPattern = "%$search%";
    $params[':search_last_name'] = $searchPattern;
    $params[':search_first_name'] = $searchPattern;
    $params[':search_email'] = $searchPattern;
    $params[':search_phone'] = $searchPattern;
}

if (!empty($genderFilter)) {
    $whereClauses[] = "gender = :gender";
    $params[':gender'] = $genderFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "status = :status";
    $params[':status'] = $statusFilter;
} else {
    // By default, exclude graduates — they appear only when explicitly filtered
    $whereClauses[] = "status NOT IN ('GRADUATE', 'DIPLOME')";
}

if (!empty($institutionFilter)) {
    $whereClauses[] = "institution LIKE :institution";
    $params[':institution'] = "%$institutionFilter%";
}

if (!empty($nationalityFilter)) {
    $whereClauses[] = "JSON_CONTAINS(nationalities, :nationality_json)";
    $params[':nationality_json'] = json_encode($nationalityFilter);
}

$whereSQL = '';
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// Récupérer le nombre total d'étudiants (pour la pagination)
$countSql = "SELECT COUNT(*) FROM students $whereSQL";
$countStmt = $conn->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalStudents = $countStmt->fetchColumn();

// Calculer le nombre total de pages
$totalPages = ceil($totalStudents / $perPage);

// Récupérer la liste des étudiants avec pagination et filtres
$sql = "SELECT * FROM students $whereSQL ORDER BY registration_date DESC LIMIT :offset, :perPage";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtenir les listes pour les filtres de sélection

// 1. Établissements
$etablissementSql = "SELECT DISTINCT institution FROM students WHERE institution IS NOT NULL AND institution <> '' ORDER BY institution";
$etablissementStmt = $conn->prepare($etablissementSql);
$etablissementStmt->execute();
$etablissements = $etablissementStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Nationalités (Extract from JSON)
$allNatsSql = "SELECT nationalities FROM students";
$allNatsStmt = $conn->query($allNatsSql);
$uniqueNats = [];
while ($row = $allNatsStmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['nationalities'])) {
        $decoded = json_decode($row['nationalities'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $nat) {
                $nat = trim($nat);
                if (!empty($nat)) {
                    $uniqueNats[] = $nat;
                }
            }
        }
    }
}
$uniqueNats = array_unique($uniqueNats);
sort($uniqueNats);


// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/students.html';
if (!is_file($layoutPath) || !is_file($contentPath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

// Générer lignes du tableau des étudiants
$rowsHtml = '';
if (count($students) > 0) {
    foreach ($students as $student) {
        $badgeClass = 'warning';
        if (in_array($student['status'], ['STUDENT', 'Étudiant'])) $badgeClass = 'primary';
        elseif (in_array($student['status'], ['PUPIL', 'Élève'])) $badgeClass = 'info';
        elseif (in_array($student['status'], ['GRADUATE', 'Diplômé', 'DIPLOME'])) $badgeClass = 'success';
        
        $rowsHtml .= '<tr class="align-middle">'
            . '<td>' . htmlspecialchars($student['last_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['gender'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';

        // Nationalities Column
        $natHtml = '';
        if (!empty($student['nationalities'])) {
            $nats = json_decode($student['nationalities'], true);
            if (is_array($nats) && count($nats) > 0) {
                 $displayNats = array_slice($nats, 0, 2);
                 foreach($displayNats as $n) {
                     $natHtml .= '<span class="badge bg-secondary me-1" style="font-size: 0.7em;">' . htmlspecialchars($n) . '</span>';
                 }
                 if(count($nats) > 2) {
                     $natHtml .= '<span class="badge bg-light text-dark" style="font-size: 0.7em;">+' . (count($nats) - 2) . '</span>';
                 }
            }
        }
        $statutCell = '<span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($student['status'] ?? '', ENT_QUOTES, 'UTF-8') . '</span>';
        if (in_array($student['status'], ['GRADUATE', 'Diplômé', 'DIPLOME']) && !empty($student['graduation_date'])) {
            $promoYear = date('Y', strtotime($student['graduation_date']));
            $statutCell .= '<br><small class="text-muted">Promo ' . $promoYear . '</small>';
        }

        $rowsHtml .= '<td>' . $natHtml . '</td>'
            . '<td>' . htmlspecialchars($student['institution'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . $statutCell . '</td>'
            . '<td class="text-nowrap">'
                . '<a href="student-details.php?id=' . (int)$student['id'] . '" class="btn btn-sm btn-outline-primary" title="Voir détails">'
                . '<i class="fas fa-eye"></i></a>'
                . '<a href="edit-student.php?id=' . (int)$student['id'] . '" class="btn btn-sm btn-outline-secondary ms-1" title="Modifier">'
                . '<i class="fas fa-edit"></i></a>'
                . '<form method="POST" action="students.php?page=' . $page . '&perPage=' . $perPage . '" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="id" value="' . (int)$student['id'] . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger ms-1 btn-delete-student" title="Supprimer">'
                . '<i class="fas fa-trash"></i>'
                . '</button>'
                . '</form>'
            . '</td>'
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="7" class="text-center">Aucun étudiant trouvé</td></tr>';
}

// Pagination
$paginationHtml = '';
if ($totalPages > 1) {
    $buildLink = function($p) use ($perPage, $search, $genderFilter, $statusFilter, $institutionFilter, $nationalityFilter) {
        return '?page=' . $p . '&perPage=' . $perPage . '&search=' . urlencode($search) . '&gender=' . urlencode($genderFilter) . '&status=' . urlencode($statusFilter) . '&institution=' . urlencode($institutionFilter) . '&nationality=' . urlencode($nationalityFilter);
    };
    $paginationHtml .= '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    $paginationHtml .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($buildLink($page - 1), ENT_QUOTES, 'UTF-8') . '">Précédent</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)) {
            $paginationHtml .= '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
            $paginationHtml .= '<a class="page-link" href="' . htmlspecialchars($buildLink($i), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
        } elseif ($i == $page - 3 || $i == $page + 3) {
            $paginationHtml .= '<li class="page-item disabled"><a class="page-link">...</a></li>';
        }
    }
    $paginationHtml .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars($buildLink($page + 1), ENT_QUOTES, 'UTF-8') . '">Suivant</a></li>';
    $paginationHtml .= '</ul></nav>';
}

// Bloc "par page"
$perPageHtml = '<div class="d-flex justify-content-center mt-3">'
    . '<form action="students.php" method="GET" class="d-flex align-items-center">'
    . '<input type="hidden" name="page" value="1">'
    . '<input type="hidden" name="search" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="gender" value="' . htmlspecialchars($genderFilter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="status" value="' . htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="institution" value="' . htmlspecialchars($institutionFilter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="nationality" value="' . htmlspecialchars($nationalityFilter, ENT_QUOTES, 'UTF-8') . '">'
    . '<label for="perPage" class="me-2">Afficher</label>'
    . '<select name="perPage" id="perPage" class="form-select form-select-sm w-auto" onchange="this.form.submit()">'
    . '<option value="10" ' . ($perPage == 10 ? 'selected' : '') . '>10</option>'
    . '<option value="25" ' . ($perPage == 25 ? 'selected' : '') . '>25</option>'
    . '<option value="50" ' . ($perPage == 50 ? 'selected' : '') . '>50</option>'
    . '<option value="100" ' . ($perPage == 100 ? 'selected' : '') . '>100</option>'
    . '</select>'
    . '<span class="ms-2">étudiants par page</span>'
    . '</form>'
    . '</div>';

// Options d'établissement
$etabOptions = '';
foreach ($etablissements as $etab) {
    $sel = ($institutionFilter == $etab) ? 'selected' : '';
    $etabOptions .= '<option value="' . htmlspecialchars($etab, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>'
        . htmlspecialchars($etab, ENT_QUOTES, 'UTF-8') . '</option>';
}

// Options de nationalité
$natOptions = '';
foreach ($uniqueNats as $nat) {
    $sel = ($nationalityFilter == $nat) ? 'selected' : '';
    $natOptions .= '<option value="' . htmlspecialchars($nat, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>'
        . htmlspecialchars($nat, ENT_QUOTES, 'UTF-8') . '</option>';
}

$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{search}}' => htmlspecialchars($search, ENT_QUOTES, 'UTF-8'),
    '{{gender_filter_Male}}' => ($genderFilter == 'Male' || $genderFilter == 'Masculin') ? 'selected' : '',
    '{{gender_filter_Female}}' => ($genderFilter == 'Female' || $genderFilter == 'Féminin') ? 'selected' : '',
    '{{status_filter_PUPIL}}' => ($statusFilter == 'PUPIL' || $statusFilter == 'Élève') ? 'selected' : '',
    '{{status_filter_STUDENT}}' => ($statusFilter == 'STUDENT' || $statusFilter == 'Étudiant') ? 'selected' : '',
    '{{status_filter_TRAINEE}}' => ($statusFilter == 'TRAINEE' || $statusFilter == 'Stagiaire') ? 'selected' : '',
    '{{status_filter_GRADUATE}}' => ($statusFilter == 'GRADUATE' || $statusFilter == 'Diplômé') ? 'selected' : '',
    '{{institution_options}}' => $etabOptions,
    '{{nationality_options}}' => $natOptions,
    '{{students_rows}}' => $rowsHtml,
    '{{pagination}}' => $paginationHtml,
    '{{per_page_block}}' => $perPageHtml,
]);

$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Liste des étudiants',
    '{{sidebar}}' => $sidebarHtml,
    '{{flash_json}}' => $flash_json,
    '{{validation_errors_json}}' => '',
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
