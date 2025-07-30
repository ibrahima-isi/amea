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
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom Color Palette CSS -->

    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <!-- Page content wrapper -->
        <div id="page-content-wrapper">
            <!-- Top navigation -->
            <nav class="navbar navbar-expand-lg navbar-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-primary" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">
                        <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($prenom . ' ' . $nom); ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="profile.php">Mon profil</a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="logout.php">Déconnexion</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Page content -->
            <div class="container-fluid p-4">
                <div class="row mb-4">
                    <div class="col">
                        <h1 class="h3">Tableau de Bord</h1>
                        <p class="text-muted">Bienvenue, <?php echo htmlspecialchars($prenom . ' ' . $nom); ?> ! Vous êtes connecté en tant que <?php echo $role == 'admin' ? 'administrateur' : 'utilisateur'; ?>.</p>
                    </div>
                </div>

                <!-- Cartes de statistiques -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total des étudiants</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hommes / Femmes</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['hommes']; ?> / <?php echo $stats['femmes']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-venus-mars fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Statut (Étudiant)</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['etudiants']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                    <h5><strong style="color: var(--light-beige);">AEESGS</strong> - Administration</h5>
                    <p>Plateforme de gestion des étudiants guinéens au Sénégal.</p>
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
                <p>&copy; <?php echo date('Y'); ?> <strong style="color: var(--light-beige);">GUI CONNECT</strong>. Tous droits réservés. | Développé par <a href="https://gui-connect.com/" target="_blank" style="color: var(--light-beige); text-decoration: none;"><strong>GUI CONNECT</strong></a></p>
            </div>
        </div>
    </footer>
</body>

</html>