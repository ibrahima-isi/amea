<?php

/**
 * Tableau de bord administrateur
 * Fichier: dashboard.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';

// Récupérer les informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
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
$countSql = "SELECT COUNT(*) FROM personne $whereSQL";
$countStmt = $conn->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalStudents = $countStmt->fetchColumn();

// Calculer le nombre total de pages
$totalPages = ceil($totalStudents / $perPage);

// Récupérer la liste des étudiants avec pagination et filtres
$sql = "SELECT * FROM personne $whereSQL ORDER BY date_enregistrement DESC LIMIT :offset, :perPage";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtenir les listes pour les filtres de sélection
$etablissementSql = "SELECT DISTINCT etablissement FROM personne ORDER BY etablissement";
$etablissementStmt = $conn->prepare($etablissementSql);
$etablissementStmt->execute();
$etablissements = $etablissementStmt->fetchAll(PDO::FETCH_COLUMN);

// Obtenir des statistiques de base
$statsSql = "SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN sexe = 'Masculin' THEN 1 ELSE 0 END) as hommes,
    SUM(CASE WHEN sexe = 'Féminin' THEN 1 ELSE 0 END) as femmes,
    SUM(CASE WHEN statut = 'Élève' THEN 1 ELSE 0 END) as eleves,
    SUM(CASE WHEN statut = 'Étudiant' THEN 1 ELSE 0 END) as etudiants,
    SUM(CASE WHEN statut = 'Stagiaire' THEN 1 ELSE 0 END) as stagiaires
FROM personne";
$statsStmt = $conn->prepare($statsSql);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Obtenir une répartition par établissement (top 5)
$etablissementStatsSql = "SELECT etablissement, COUNT(*) as nombre 
                         FROM personne 
                         GROUP BY etablissement 
                         ORDER BY nombre DESC 
                         LIMIT 5";
$etablissementStatsStmt = $conn->prepare($etablissementStatsSql);
$etablissementStatsStmt->execute();
$etablissementStats = $etablissementStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Titre de la page
$pageTitle = "AEESGS - Tableau de Bord";

// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/dashboard.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

// Générer lignes du tableau des étudiants
$rowsHtml = '';
if (count($students) > 0) {
    foreach ($students as $student) {
        $badgeClass = 'warning';
        if ($student['statut'] == 'Étudiant') $badgeClass = 'primary';
        elseif ($student['statut'] == 'Élève') $badgeClass = 'info';
        $rowsHtml .= '<tr>'
            . '<td>' . (int)$student['id_personne'] . '</td>'
            . '<td>' . htmlspecialchars($student['nom'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['prenom'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['sexe'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['age'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($student['etablissement'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($student['statut'], ENT_QUOTES, 'UTF-8') . '</span></td>'
            . '<td>'
                . '<a href="mailto:' . htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') . '" class="text-primary" title="Email">'
                . '<i class="fas fa-envelope"></i></a>'
                . '<a href="tel:' . htmlspecialchars($student['telephone'], ENT_QUOTES, 'UTF-8') . '" class="text-success ms-2" title="Téléphone">'
                . '<i class="fas fa-phone"></i></a>'
            . '</td>'
            . '<td>'
                . '<a href="student-details.php?id=' . (int)$student['id_personne'] . '" class="btn btn-sm btn-outline-primary" title="Voir détails">'
                . '<i class="fas fa-eye"></i></a>'
                . '<a href="edit-student.php?id=' . (int)$student['id_personne'] . '" class="btn btn-sm btn-outline-secondary ms-1" title="Modifier">'
                . '<i class="fas fa-edit"></i></a>'
            . '</td>'
            . '</tr>';
    }
} else {
    $rowsHtml = '<tr><td colspan="9" class="text-center">Aucun étudiant trouvé</td></tr>';
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
    . '<form action="dashboard.php" method="GET" class="d-flex align-items-center">'
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

// Données pour graphiques (top écoles)
$labels = [];
$values = [];
foreach ($etablissementStats as $stat) {
    $labels[] = "'" . addslashes($stat['etablissement']) . "'";
    $values[] = (int)$stat['nombre'];
}

// Remplir le contenu spécifique
$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    '{{role_label}}' => $role == 'admin' ? 'administrateur' : 'utilisateur',
    '{{stats_total}}' => (string)$stats['total'],
    '{{stats_hommes}}' => (string)$stats['hommes'],
    '{{stats_femmes}}' => (string)$stats['femmes'],
    '{{stats_etudiants}}' => (string)$stats['etudiants'],
    '{{stats_eleves}}' => (string)$stats['eleves'],
    '{{stats_stagiaires}}' => (string)$stats['stagiaires'],
    '{{recent_week}}' => (string)(function() use ($conn) { $q = $conn->prepare("SELECT COUNT(*) FROM personne WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); $q->execute(); return $q->fetchColumn(); })(),
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
    '{{school_labels}}' => implode(', ', $labels),
    '{{school_values}}' => implode(', ', $values),
]);

// Remplir le layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Tableau de Bord',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;
exit();

                    
