<?php

/**
 * Page de profil administrateur
 * Fichier: profile.php
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

// Initialiser les variables d'erreur et de succès
$error = "";
$success = "";

// Récupérer les informations complètes de l'utilisateur
try {
    $sql = "SELECT * FROM user WHERE id_user = :id_user";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Erreur lors de la récupération du profil utilisateur", $e);
    $error = "Impossible de récupérer les informations de profil pour le moment.";
}

$csrfToken = generateCsrfToken();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "La session a expiré. Veuillez réessayer.";
    } elseif (isset($_POST['update_profile'])) {
        $newNom = trim($_POST['nom'] ?? '');
        $newPrenom = trim($_POST['prenom'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');

        if (empty($newNom) || empty($newPrenom) || empty($newEmail)) {
            $error = "Tous les champs sont obligatoires.";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez entrer une adresse email valide.";
        } else {
            try {
                $checkSql = "SELECT COUNT(*) FROM user WHERE email = :email AND id_user != :id_user";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bindParam(':email', $newEmail);
                $checkStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $error = "Cette adresse email est déjà utilisée par un autre utilisateur.";
                } else {
                    $updateSql = "UPDATE user SET nom = :nom, prenom = :prenom, email = :email WHERE id_user = :id_user";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':nom', $newNom);
                    $updateStmt->bindParam(':prenom', $newPrenom);
                    $updateStmt->bindParam(':email', $newEmail);
                    $updateStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    $_SESSION['nom'] = $newNom;
                    $_SESSION['prenom'] = $newPrenom;

                    $sql = "SELECT * FROM user WHERE id_user = :id_user";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    $success = "Votre profil a été mis à jour avec succès.";
                    $error = "";
                }
            } catch (PDOException $e) {
                logError("Erreur lors de la mise à jour du profil utilisateur", $e);
                $error = "Une erreur est survenue lors de la mise à jour du profil. Veuillez réessayer plus tard.";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "Tous les champs de mot de passe sont obligatoires.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        } elseif (strlen($newPassword) < 8) {
            $error = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } else {
            try {
                if (password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $updateSql = "UPDATE user SET password = :password WHERE id_user = :id_user";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':password', $hashedPassword);
                    $updateStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    $success = "Votre mot de passe a été changé avec succès.";
                    $error = "";
                } else {
                    $error = "Le mot de passe actuel est incorrect.";
                }
            } catch (PDOException $e) {
                logError("Erreur lors du changement de mot de passe", $e);
                $error = "Une erreur est survenue lors du changement de mot de passe. Veuillez réessayer plus tard.";
            }
        }
    }

    $csrfToken = generateCsrfToken();
}

// Titre de la page
$pageTitle = "AEESGS - Profil Administrateur";
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
            border-color: rgba(255, 255, 255, 0.1);
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

        /* Custom button styles */
        .btn-primary {
            background-color: var(--medium-blue) !important;
            border-color: var(--medium-blue) !important;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--dark-blue) !important;
            border-color: var(--dark-blue) !important;
        }

        .btn-outline-light:hover {
            color: var(--dark-blue) !important;
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

        /* Form controls */
        .form-control:focus,
        .form-select:focus {
            border-color: var(--light-blue) !important;
            box-shadow: 0 0 0 0.25rem rgba(148, 180, 193, 0.25) !important;
        }

        /* Avatar placeholder */
        .avatar-placeholder {
            background-color: var(--medium-blue) !important;
        }

        /* Badge colors */
        .bg-success {
            background-color: var(--light-blue) !important;
        }

        .bg-danger {
            background-color: #dc3545 !important;
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
    </style>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <?php include 'includes/sidebar.php'; ?> <!-- Page content wrapper -->
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
                        <h1 class="h3">Profil Administrateur</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Tableau de bord</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Profil</li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Informations du compte -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow">
                            <div class="card-header">
                                <h5 class="m-0 font-weight-bold">Informations du compte</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="avatar-placeholder rounded-circle mx-auto mb-3" style="width: 100px; height: 100px; line-height: 100px; font-size: 40px;">
                                        <?php echo htmlspecialchars(strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1))); ?>
                                    </div>
                                    <h4 class="font-weight-bold"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h4>
                                    <p class="mb-1 text-muted">
                                        <?php echo ($user['role'] ?? '') === 'admin' ? 'Administrateur' : 'Utilisateur'; ?>
                                    </p>
                                    <p class="badge bg-<?php echo $user['est_actif'] ? 'success' : 'danger'; ?>">
                                        <?php echo $user['est_actif'] ? 'Compte actif' : 'Compte inactif'; ?>
                                    </p>
                                </div>
                                <hr>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">ID:</div>
                                    <div class="col-7"><?php echo isset($user['id_user']) ? (int)$user['id_user'] : ''; ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Nom d'utilisateur:</div>
                                    <div class="col-7"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Email:</div>
                                    <div class="col-7"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Date de création:</div>
                                    <div class="col-7"><?php echo formatDateFr($user['date_creation'], true); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-5 text-secondary">Dernière connexion:</div>
                                    <div class="col-7">
                                        <?php echo $user['derniere_connexion'] ? formatDateFr($user['derniere_connexion'], true) : 'Jamais'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modifier le profil -->
                    <div class="col-md-8">
                        <div class="row">
                            <!-- Formulaire de mise à jour du profil -->
                            <div class="col-12 mb-4">
                                <div class="card shadow">
                                    <div class="card-header">
                                        <h5 class="m-0 font-weight-bold">Modifier le profil</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="nom" class="form-label">Nom</label>
                                                    <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="prenom" class="form-label">Prénom</label>
                                                    <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                                <div class="form-text text-muted">Le nom d'utilisateur ne peut pas être modifié.</div>
                                            </div>
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Mettre à jour le profil
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Formulaire de changement de mot de passe -->
                            <div class="col-12 mb-4">
                                <div class="card shadow">
                                    <div class="card-header">
                                        <h5 class="m-0 font-weight-bold">Changer le mot de passe</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="passwordForm">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <div class="form-text text-muted">Le mot de passe doit contenir au moins 8 caractères.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                            <button type="submit" name="change_password" class="btn btn-primary">
                                                <i class="fas fa-key"></i> Changer le mot de passe
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
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

        // Validation du formulaire de mot de passe
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            var newPassword = document.getElementById('new_password').value;
            var confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword != confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
            }

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
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
                        <li><a href="profile.php" class="text-white">Mon profil</a></li>
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