<?php
/**
 * Page de détails d'un étudiant
 * Fichier: student-details.php
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
require_once 'functions/utility-functions.php';

// Récupérer les informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Vérifier si un ID étudiant est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Rediriger vers le tableau de bord s'il n'y a pas d'ID
    header("Location: dashboard.php");
    exit();
}

$student_id = (int)$_GET['id'];

// Récupérer les détails de l'étudiant depuis la base de données
try {
    $sql = "SELECT * FROM personne WHERE id_personne = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // L'étudiant n'existe pas, rediriger vers le tableau de bord
        setFlashMessage('error', 'L\'étudiant demandé n\'existe pas.');
        header("Location: dashboard.php");
        exit();
    }
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // En cas d'erreur, afficher un message d'erreur
    setFlashMessage('error', 'Erreur lors de la récupération des détails de l\'étudiant: ' . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Titre de la page
$pageTitle = "Détails de l'étudiant - " . $student['prenom'] . ' ' . $student['nom'];
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
    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <!-- Custom Color Palette CSS -->
    <style>
        :root {
            --dark-blue: #213448;
            --medium-blue: #547792;
            --light-blue: #94B4C1;
            --pale-yellow: #ECEFCA;
        }
        
        body {
            background-color: #f8f8fa;
            color: var(--dark-blue);
        }
        
        /* Sidebar styling */
        #sidebar-wrapper {
            background-color: var(--dark-blue);
        }
        
        #sidebar-wrapper .list-group-item {
            background-color: transparent;
            color: white;
            border-color: rgba(255,255,255,0.1);
        }
        
        #sidebar-wrapper .list-group-item.active, 
        #sidebar-wrapper .list-group-item:hover {
            background-color: var(--medium-blue);
        }
        
        /* Navbar styling */
        .navbar {
            background-color: white !important;
            border-bottom: 1px solid var(--light-blue) !important;
        }
        
        /* Breadcrumb styling */
        .breadcrumb-item a {
            color: var(--medium-blue);
        }
        
        .breadcrumb-item.active {
            color: var(--dark-blue);
        }
        
        /* Card styling */
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 52, 72, 0.1);
        }
        
        .card-header {
            background-color: var(--dark-blue) !important;
            color: white !important;
        }
        
        .card-body {
            background-color: white;
        }
        
        /* Alert styling */
        .alert-success {
            background-color: rgba(236, 239, 202, 0.2);
            border-color: var(--pale-yellow);
            color: var(--dark-blue);
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
            color: var(--dark-blue);
        }
        
        /* Button styling */
        .btn-primary {
            background-color: var(--medium-blue) !important;
            border-color: var(--medium-blue) !important;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--dark-blue) !important;
            border-color: var(--dark-blue) !important;
        }
        
        .btn-outline-primary {
            color: var(--medium-blue) !important;
            border-color: var(--medium-blue) !important;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--medium-blue) !important;
            color: white !important;
        }
        
        .btn-outline-secondary {
            color: var(--dark-blue) !important;
            border-color: var(--dark-blue) !important;
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--dark-blue) !important;
            color: white !important;
        }
        
        /* Keep the danger button for delete actions */
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        
        /* Badge colors */
        .bg-primary {
            background-color: var(--medium-blue) !important;
        }
        
        .bg-info {
            background-color: var(--light-blue) !important;
        }
        
        .bg-warning {
            background-color: var(--pale-yellow) !important;
            color: var(--dark-blue) !important;
        }
        
        .bg-danger {
            background-color: #dc3545 !important;
        }
        
        /* Avatar placeholder */
        .avatar-placeholder {
            background-color: var(--medium-blue) !important;
        }
        
        /* Text colors */
        .text-primary {
            color: var(--medium-blue) !important;
        }
        
        .text-secondary {
            color: var(--dark-blue) !important;
            opacity: 0.7;
        }
        
        .text-muted {
            color: var(--dark-blue) !important;
            opacity: 0.6;
        }
        
        /* Background colors */
        .bg-light {
            background-color: rgba(148, 180, 193, 0.1) !important;
        }
        
        /* Modal styling */
        .modal-header.bg-danger {
            background-color: #dc3545 !important;
        }
        
        /* Print styling - will only affect when printing */
        @media print {
            .navbar, .btn, #sidebarToggle, .modal, footer {
                display: none !important;
            }
            
            .card {
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
            }
            
            .card-header {
                background-color: #f8f9fa !important;
                color: #212529 !important;
                border-bottom: 1px solid #dee2e6 !important;
            }
            
            .container-fluid {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            body {
                background-color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
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
                    <div class="col-md-8">
                        <h1 class="h3">Détails de l'étudiant</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Détails de l'étudiant</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="edit-student.php?id=<?php echo $student_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="#" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </div>

                <!-- Affichage du message flash s'il existe -->
                <?php $flashMessage = getFlashMessage(); ?>
                <?php if ($flashMessage): ?>
                    <div class="alert <?php echo getFlashMessageClass($flashMessage['type']); ?> alert-dismissible fade show" role="alert">
                        <?php echo $flashMessage['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Informations de base -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header">
                                <h5 class="m-0 font-weight-bold">Informations de base</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="avatar-placeholder rounded-circle text-white mx-auto mb-3" style="width: 100px; height: 100px; line-height: 100px; font-size: 40px;">
                                        <?php echo strtoupper(substr($student['prenom'], 0, 1) . substr($student['nom'], 0, 1)); ?>
                                    </div>
                                    <h4 class="font-weight-bold"><?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?></h4>
                                    <p class="mb-1 text-muted"><?php echo $student['statut']; ?></p>
                                    <p class="badge bg-<?php 
                                        if ($student['sexe'] == 'Masculin') echo 'primary';
                                        else echo 'danger';
                                    ?>">
                                        <?php echo $student['sexe']; ?>
                                    </p>
                                </div>
                                <hr>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">ID:</div>
                                    <div class="col-7"><?php echo $student['id_personne']; ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Âge:</div>
                                    <div class="col-7"><?php echo $student['age']; ?> ans</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Date de naissance:</div>
                                    <div class="col-7"><?php echo formatDateFr($student['date_naissance']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Date d'enregistrement:</div>
                                    <div class="col-7"><?php echo formatDateFr($student['date_enregistrement'], true); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informations de contact -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header">
                                <h5 class="m-0 font-weight-bold">Contact et résidence</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Téléphone:</div>
                                    <div class="col-8">
                                        <a href="tel:<?php echo htmlspecialchars($student['telephone']); ?>" class="text-decoration-none">
                                            <i class="fas fa-phone-alt me-1 text-primary"></i> <?php echo htmlspecialchars($student['telephone']); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Email:</div>
                                    <div class="col-8">
                                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" class="text-decoration-none">
                                            <i class="fas fa-envelope me-1 text-primary"></i> <?php echo htmlspecialchars($student['email']); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Résidence:</div>
                                    <div class="col-8"><?php echo htmlspecialchars($student['lieu_residence']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Arrivée au Sénégal:</div>
                                    <div class="col-8"><?php echo $student['annee_arrivee'] ?: 'Non spécifié'; ?></div>
                                </div>
                                <hr>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Type de logement:</div>
                                    <div class="col-7"><?php echo $student['type_logement']; ?></div>
                                </div>
                                <?php if (!empty($student['precision_logement'])): ?>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Précisions:</div>
                                    <div class="col-7"><?php echo htmlspecialchars($student['precision_logement']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Informations académiques -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow h-100">
                            <div class="card-header">
                                <h5 class="m-0 font-weight-bold">Informations académiques</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Établissement:</div>
                                    <div class="col-8"><?php echo htmlspecialchars($student['etablissement']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Statut:</div>
                                    <div class="col-8">
                                        <span class="badge bg-<?php 
                                            if ($student['statut'] == 'Étudiant') echo 'primary';
                                            elseif ($student['statut'] == 'Élève') echo 'info';
                                            else echo 'warning';
                                        ?>">
                                            <?php echo $student['statut']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Domaine:</div>
                                    <div class="col-8"><?php echo htmlspecialchars($student['domaine_etudes']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4 text-secondary">Niveau:</div>
                                    <div class="col-8"><?php echo htmlspecialchars($student['niveau_etudes']); ?></div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-12 text-secondary">Projet après formation:</div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mt-2">
                                        <div class="border rounded p-3 bg-light">
                                            <?php if (!empty($student['projet_apres_formation'])): ?>
                                                <?php echo nl2br(htmlspecialchars($student['projet_apres_formation'])); ?>
                                            <?php else: ?>
                                                <em class="text-muted">Non spécifié</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="m-0 font-weight-bold">Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <a href="dashboard.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <a href="edit-student.php?id=<?php echo $student_id; ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-edit"></i> Modifier les informations
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <button type="button" class="btn btn-outline-primary w-100" onclick="printStudentDetails()">
                                            <i class="fas fa-print"></i> Imprimer la fiche
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmation de suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer définitivement cet étudiant ? Cette action est irréversible.</p>
                    <p class="fw-bold">
                        <?php echo htmlspecialchars($student['prenom'] . ' ' . $student['nom']); ?> 
                        (<?php echo $student['email']; ?>)
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form action="delete-student.php" method="POST">
                        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Supprimer définitivement
                        </button>
                    </form>
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
        
        // Fonction pour imprimer la fiche étudiant
        function printStudentDetails() {
            window.print();
        }
    </script>
</body>
</html>