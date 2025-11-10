<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Paramètres de pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 10;
$offset = ($page - 1) * $perPage;

// Paramètres de filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sexeFilter = isset($_GET['sexe']) ? $_GET['sexe'] : '';
$statutFilter = isset($_GET['statut']) ? $_GET['statut'] : '';
$etablissementFilter = isset($_GET['etablissement']) ? $_GET['etablissement'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (isset($_POST['id']) && is_numeric($_POST['id'])) {
        $student_id_to_delete = (int)$_POST['id'];

        // First, get the photo path to delete the file
        $stmt = $conn->prepare("SELECT identite FROM personnes WHERE id_personne = ?");
        $stmt->execute([$student_id_to_delete]);
        $identite_to_delete = $stmt->fetchColumn();

        // Delete the student from the database
        $deleteStmt = $conn->prepare("DELETE FROM personnes WHERE id_personne = ?");
        $deleteStmt->execute([$student_id_to_delete]);

        // Delete the photo file if it exists
        if ($identite_to_delete && file_exists($identite_to_delete)) {
            unlink($identite_to_delete);
        }

        // Redirect to the same page to see the changes
        header("Location: students.php?page=$page&perPage=$perPage&search=$search&sexe=$sexeFilter&statut=$statutFilter&etablissement=$etablissementFilter");
        exit();
    }
}

// Construire la requête SQL avec les filtres
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(nom LIKE :search OR prenom LIKE :search OR email LIKE :search OR telephone LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($sexeFilter)) {
    $whereClauses[] = "sexe = :sexe";
    $params[':sexe'] = $sexeFilter;
}

if (!empty($statutFilter)) {
    $whereClauses[] = "statut = :statut";
    $params[':statut'] = $statutFilter;
}

if (!empty($etablissementFilter)) {
    $whereClauses[] = "etablissement LIKE :etablissement";
    $params[':etablissement'] = "%$etablissementFilter%";
}

$whereSQL = '';
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(' AND ', $whereClauses);
}

// Récupérer le nombre total d'étudiants (pour la pagination)
$countSql = "SELECT COUNT(*) FROM personnes $whereSQL";
$countStmt = $conn->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalStudents = $countStmt->fetchColumn();

// Calculer le nombre total de pages
$totalPages = ceil($totalStudents / $perPage);

// Récupérer la liste des étudiants avec pagination et filtres
$sql = "SELECT * FROM personnes $whereSQL ORDER BY date_enregistrement DESC LIMIT :offset, :perPage";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtenir les listes pour les filtres de sélection
$etablissementSql = "SELECT DISTINCT etablissement FROM personnes ORDER BY etablissement";
$etablissementStmt = $conn->prepare($etablissementSql);
$etablissementStmt->execute();
$etablissements = $etablissementStmt->fetchAll(PDO::FETCH_COLUMN);

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

// ... (previous code)

// Générer lignes du tableau des étudiants
$rowsHtml = '';
if (count($students) > 0) {
    foreach ($students as $student) {
        $badgeClass = 'warning';
        if ($student['statut'] == 'Étudiant') $badgeClass = 'primary';
        elseif ($student['statut'] == 'Élève') $badgeClass = 'info';
        $photoHtml = '<img src="assets/img/placeholder.png" alt="Photo" class="img-thumbnail" width="50">';
        if (!empty($student['identite']) && file_exists($student['identite'])) {
            $modalId = 'photoModal' . $student['id_personne'];
            $photoHtml = '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">';
            $photoHtml .= '<img src="' . htmlspecialchars($student['identite'], ENT_QUOTES, 'UTF-8') . '" alt="Photo" class="img-thumbnail" width="50">';
            $photoHtml .= '</a>';

            // Add the modal HTML
            $photoHtml .= '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-labelledby="' . $modalId . 'Label" aria-hidden="true">';
            $photoHtml .= '<div class="modal-dialog modal-dialog-centered">';
            $photoHtml .= '<div class="modal-content">';
            $photoHtml .= '<div class="modal-header">';
            $photoHtml .= '<h5 class="modal-title" id="' . $modalId . 'Label">' . htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8') . '</h5>';
            $photoHtml .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>';
            $photoHtml .= '</div>';
            $photoHtml .= '<div class="modal-body text-center">';
            $photoHtml .= '<img src="' . htmlspecialchars($student['identite'], ENT_QUOTES, 'UTF-8') . '" class="img-fluid">';
            $photoHtml .= '</div>';
            $photoHtml .= '</div>';
            $photoHtml .= '</div>';
            $photoHtml .= '</div>';
        }
        $rowsHtml .= '<tr>'
            . '<td>' . $photoHtml . '</td>'
            . '<td>' . (int)$student['id_personne'] . '</td>'
            . '<td>' . htmlspecialchars($student['nom'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['prenom'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['sexe'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['etablissement'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($student['statut'], ENT_QUOTES, 'UTF-8') . '</span></td>'
            . '<td><a href="mailto:' . htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') . '" class="text-primary">' . htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') . '</a></td>'
            . '<td><a href="tel:' . htmlspecialchars($student['telephone'], ENT_QUOTES, 'UTF-8') . '" class="text-success">' . htmlspecialchars($student['telephone'], ENT_QUOTES, 'UTF-8') . '</a></td>'
            . '<td>'
                . '<a href="student-details.php?id=' . (int)$student['id_personne'] . '" class="btn btn-sm btn-outline-primary" title="Voir détails">'
                . '<i class="fas fa-eye"></i></a>'
                . '<a href="edit-student.php?id=' . (int)$student['id_personne'] . '" class="btn btn-sm btn-outline-secondary ms-1" title="Modifier">'
                . '<i class="fas fa-edit"></i></a>'
                . '<form method="POST" action="students.php?page=' . $page . '&perPage=' . $perPage . '" class="d-inline">'
                . '<input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="id" value="' . (int)$student['id_personne'] . '">'
                . '<button type="submit" class="btn btn-sm btn-outline-danger ms-1 btn-delete-student" title="Supprimer">'
                . '<i class="fas fa-trash"></i>'
                . '</button>'
                . '</form>'
            . '</td>'
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="10" class="text-center">Aucun étudiant trouvé</td></tr>';
}

// Pagination
$paginationHtml = '';
if ($totalPages > 1) {
    $buildLink = function($p) use ($perPage, $search, $sexeFilter, $statutFilter, $etablissementFilter) {
        return '?page=' . $p . '&perPage=' . $perPage . '&search=' . urlencode($search) . '&sexe=' . urlencode($sexeFilter) . '&statut=' . urlencode($statutFilter) . '&etablissement=' . urlencode($etablissementFilter);
    };
    $paginationHtml .= '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    $paginationHtml .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $buildLink($page - 1) . '">Précédent</a></li>';
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)) {
            $paginationHtml .= '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
            $paginationHtml .= '<a class="page-link" href="' . $buildLink($i) . '">' . $i . '</a></li>';
        } elseif ($i == $page - 3 || $i == $page + 3) {
            $paginationHtml .= '<li class="page-item disabled"><a class="page-link">...</a></li>';
        }
    }
    $paginationHtml .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="' . $buildLink($page + 1) . '">Suivant</a></li>';
    $paginationHtml .= '</ul></nav>';
}

// Bloc "par page"
$perPageHtml = '<div class="d-flex justify-content-center mt-3">'
    . '<form action="students.php" method="GET" class="d-flex align-items-center">'
    . '<input type="hidden" name="page" value="1">'
    . '<input type="hidden" name="search" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="sexe" value="' . htmlspecialchars($sexeFilter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="statut" value="' . htmlspecialchars($statutFilter, ENT_QUOTES, 'UTF-8') . '">'
    . '<input type="hidden" name="etablissement" value="' . htmlspecialchars($etablissementFilter, ENT_QUOTES, 'UTF-8') . '">'
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
    $sel = ($etablissementFilter == $etab) ? 'selected' : '';
    $etabOptions .= '<option value="' . htmlspecialchars($etab, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>'
        . htmlspecialchars($etab, ENT_QUOTES, 'UTF-8') . '</option>';
}

$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{search}}' => htmlspecialchars($search, ENT_QUOTES, 'UTF-8'),
    '{{sexe_filter_Masculin}}' => ($sexeFilter == 'Masculin') ? 'selected' : '',
    '{{sexe_filter_Féminin}}' => ($sexeFilter == 'Féminin') ? 'selected' : '',
    '{{statut_filter_Élève}}' => ($statutFilter == 'Élève') ? 'selected' : '',
    '{{statut_filter_Étudiant}}' => ($statutFilter == 'Étudiant') ? 'selected' : '',
    '{{statut_filter_Stagiaire}}' => ($statutFilter == 'Stagiaire') ? 'selected' : '',
    '{{etablissement_options}}' => $etabOptions,
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
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;
