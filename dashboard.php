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

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Derniers ajouts</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php
                                            // Obtenir le nombre d'ajouts dans les 7 derniers jours
                                            $recentSql = "SELECT COUNT(*) FROM personne WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                                            $recentStmt = $conn->prepare($recentSql);
                                            $recentStmt->execute();
                                            echo $recentStmt->fetchColumn();
                                            ?>
                                        </div>
                                        <div class="text-xs text-muted">(7 derniers jours)</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Graphiques -->
                <div class="row mb-4">
                    <div class="col-xl-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Répartition par sexe</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="genderChart" width="100%" height="40"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Répartition par statut</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" width="100%" height="40"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card shadow">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold">Top 5 des établissements</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="schoolChart" width="100%" height="30"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres et liste des étudiants -->
                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold">Liste des étudiants</h6>
                        <a href="export.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-file-export"></i> Exporter
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Filtres -->
                        <form method="GET" action="dashboard.php" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="sexe">
                                        <option value="">Tous les sexes</option>
                                        <option value="Masculin" <?php if ($sexeFilter == 'Masculin') echo 'selected'; ?>>Masculin</option>
                                        <option value="Féminin" <?php if ($sexeFilter == 'Féminin') echo 'selected'; ?>>Féminin</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="statut">
                                        <option value="">Tous les statuts</option>
                                        <option value="Élève" <?php if ($statutFilter == 'Élève') echo 'selected'; ?>>Élève</option>
                                        <option value="Étudiant" <?php if ($statutFilter == 'Étudiant') echo 'selected'; ?>>Étudiant</option>
                                        <option value="Stagiaire" <?php if ($statutFilter == 'Stagiaire') echo 'selected'; ?>>Stagiaire</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="etablissement">
                                        <option value="">Tous les établissements</option>
                                        <?php foreach ($etablissements as $etab): ?>
                                            <option value="<?php echo htmlspecialchars($etab); ?>" <?php if ($etablissementFilter == $etab) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($etab); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                                </div>
                            </div>
                        </form>

                        <!-- Tableau des étudiants -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Sexe</th>
                                        <th>Âge</th>
                                        <th>Établissement</th>
                                        <th>Statut</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo $student['id_personne']; ?></td>
                                                <td><?php echo htmlspecialchars($student['nom']); ?></td>
                                                <td><?php echo htmlspecialchars($student['prenom']); ?></td>
                                                <td><?php echo $student['sexe']; ?></td>
                                                <td><?php echo $student['age']; ?></td>
                                                <td><?php echo htmlspecialchars($student['etablissement']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            if ($student['statut'] == 'Étudiant') echo 'primary';
                                                                            elseif ($student['statut'] == 'Élève') echo 'info';
                                                                            else echo 'warning';
                                                                            ?>">
                                                        <?php echo $student['statut']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="text-primary" title="Email">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                    <a href="tel:<?php echo htmlspecialchars($student['telephone']); ?>" class="text-success ms-2" title="Téléphone">
                                                        <i class="fas fa-phone"></i>
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="student-details.php?id=<?php echo $student['id_personne']; ?>" class="btn btn-sm btn-outline-primary" title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit-student.php?id=<?php echo $student['id_personne']; ?>" class="btn btn-sm btn-outline-secondary" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">Aucun étudiant trouvé</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&perPage=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&sexe=<?php echo urlencode($sexeFilter); ?>&statut=<?php echo urlencode($statutFilter); ?>&etablissement=<?php echo urlencode($etablissementFilter); ?>">
                                            Précédent
                                        </a>
                                    </li>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php
                                        // Afficher uniquement les pages près de la page actuelle
                                        if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)) {
                                            echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                            echo '<a class="page-link" href="?page=' . $i . '&perPage=' . $perPage . '&search=' . urlencode($search) . '&sexe=' . urlencode($sexeFilter) . '&statut=' . urlencode($statutFilter) . '&etablissement=' . urlencode($etablissementFilter) . '">' . $i . '</a>';
                                            echo '</li>';
                                        } elseif ($i == $page - 3 || $i == $page + 3) {
                                            echo '<li class="page-item disabled"><a class="page-link">...</a></li>';
                                        }
                                        ?>
                                    <?php endfor; ?>

                                    <li class="page-item <?php if ($page >= $totalPages) echo 'disabled'; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&perPage=<?php echo $perPage; ?>&search=<?php echo urlencode($search); ?>&sexe=<?php echo urlencode($sexeFilter); ?>&statut=<?php echo urlencode($statutFilter); ?>&etablissement=<?php echo urlencode($etablissementFilter); ?>">
                                            Suivant
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <!-- Affichage par page -->
                        <div class="d-flex justify-content-center mt-3">
                            <form action="dashboard.php" method="GET" class="d-flex align-items-center">
                                <input type="hidden" name="page" value="1">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="sexe" value="<?php echo htmlspecialchars($sexeFilter); ?>">
                                <input type="hidden" name="statut" value="<?php echo htmlspecialchars($statutFilter); ?>">
                                <input type="hidden" name="etablissement" value="<?php echo htmlspecialchars($etablissementFilter); ?>">
                                <label for="perPage" class="me-2">Afficher</label>
                                <select name="perPage" id="perPage" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                                    <option value="10" <?php if ($perPage == 10) echo 'selected'; ?>>10</option>
                                    <option value="25" <?php if ($perPage == 25) echo 'selected'; ?>>25</option>
                                    <option value="50" <?php if ($perPage == 50) echo 'selected'; ?>>50</option>
                                    <option value="100" <?php if ($perPage == 100) echo 'selected'; ?>>100</option>
                                </select>
                                <span class="ms-2">étudiants par page</span>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personnalisés -->
    <script src="assets/js/dashboard.js"></script>

    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('wrapper').classList.toggle('toggled');
        });

        // Chart.js - Graphique par sexe
        var genderCtx = document.getElementById('genderChart').getContext('2d');
        var genderChart = new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: ['Masculin', 'Féminin'],
                datasets: [{
                    data: [<?php echo $stats['hommes']; ?>, <?php echo $stats['femmes']; ?>],
                    backgroundColor: ['#547792', '#94B4C1'],
                    hoverBackgroundColor: ['#213448', '#7999A8'],
                    hoverBorderColor: "#ECEFCA",
                }],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw || 0;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            },
        });

        // Chart.js - Graphique par statut
        var statusCtx = document.getElementById('statusChart').getContext('2d');
        var statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Étudiants', 'Élèves', 'Stagiaires'],
                datasets: [{
                    data: [<?php echo $stats['etudiants']; ?>, <?php echo $stats['eleves']; ?>, <?php echo $stats['stagiaires']; ?>],
                    backgroundColor: ['#547792', '#94B4C1', '#ECEFCA'],
                    hoverBackgroundColor: ['#213448', '#7999A8', '#D9DCB8'],
                    hoverBorderColor: "#FFFFFF",
                }],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.raw || 0;
                                var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                var percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            },
        });

        // Chart.js - Graphique des établissements
        var schoolCtx = document.getElementById('schoolChart').getContext('2d');
        var schoolChart = new Chart(schoolCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php
                    foreach ($etablissementStats as $stat) {
                        echo "'" . addslashes($stat['etablissement']) . "', ";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Nombre d\'étudiants',
                    data: [
                        <?php
                        foreach ($etablissementStats as $stat) {
                            echo $stat['nombre'] . ", ";
                        }
                        ?>
                    ],
                    backgroundColor: '#547792',
                    hoverBackgroundColor: '#213448',
                    borderColor: '#547792',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row align-items-start">
                <div class="col-md-4 text-center d-flex flex-column justify-content-start">
                    <h5><strong class="text-light-beige">AEESGS</strong> - Administration</h5>
                    <p>Plateforme de gestion des élèves, étudiants et stagiaires guinéens au Sénégal.</p>
                </div>
                <div class="col-md-4 text-center d-flex flex-column justify-content-start">
                    <h5>Liens rapides</h5>
                    <ul class="list-unstyled">
                        <li><a href="dashboard.php" class="text-white">Tableau de bord</a></li>
                        <li><a href="users.php" class="text-white">Gestion des utilisateurs</a></li>
                        <li><a href="export.php" class="text-white">Exporter</a></li>
                    </ul>
                </div>
                <div class="col-md-4 text-center d-flex flex-column justify-content-start">
                    <h5>Contact</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> admin@aeesgs.org</li>
                        <li><i class="fas fa-phone me-2"></i> +221 XX XXX XX XX</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <strong class="text-light-beige">GUI CONNECT</strong>. Tous droits réservés. | Développé par <a href="https://gui-connect.com/" target="_blank" class="link-light-beige"><strong>GUI CONNECT</strong></a></p>
            </div>
        </div>
    </footer>
</body>

</html>
