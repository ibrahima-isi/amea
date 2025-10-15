<?php

/**
 * Page de gestion des utilisateurs
 * Fichier: users.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Initialiser les variables
$message = "";
$messageType = "";
$users = [];
$csrfToken = generateCsrfToken();

// Fonction pour obtenir la liste des utilisateurs
function getUsers($conn)
{
    try {
        $sql = "SELECT id_user, username, nom, prenom, email, role, est_actif, derniere_connexion, date_creation
                FROM user ORDER BY id_user DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError('Erreur lors de la récupération des utilisateurs', $e);
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = "La session a expiré. Veuillez réessayer.";
        $messageType = "warning";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        switch ($action) {
            case 'delete':
                if ($userId !== (int)$_SESSION['user_id']) {
                    try {
                        $sql = "DELETE FROM user WHERE id_user = :id_user";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                        $stmt->execute();

                        $message = "L'utilisateur a été supprimé avec succès.";
                        $messageType = "success";
                    } catch (PDOException $e) {
                        logError("Erreur lors de la suppression d'un utilisateur", $e);
                        $message = "Une erreur est survenue lors de la suppression de l'utilisateur.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Vous ne pouvez pas supprimer votre propre compte.";
                    $messageType = "warning";
                }
                break;

            case 'toggle':
                $currentStatus = isset($_POST['status']) ? (int)$_POST['status'] : 0;
                $newStatus = $currentStatus ? 0 : 1;

                if ($userId !== (int)$_SESSION['user_id'] || $newStatus === 1) {
                    try {
                        $sql = "UPDATE user SET est_actif = :est_actif WHERE id_user = :id_user";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':est_actif', $newStatus, PDO::PARAM_INT);
                        $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                        $stmt->execute();

                        $statusText = $newStatus ? "activé" : "désactivé";
                        $message = "L'utilisateur a été " . $statusText . " avec succès.";
                        $messageType = "success";
                    } catch (PDOException $e) {
                        logError("Erreur lors du changement de statut d'un utilisateur", $e);
                        $message = "Une erreur est survenue lors de la modification du statut.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Vous ne pouvez pas désactiver votre propre compte.";
                    $messageType = "warning";
                }
                break;

            case 'reset':
                $tempPassword = bin2hex(random_bytes(4));
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

                try {
                    $sql = "UPDATE user SET password = :password WHERE id_user = :id_user";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':password', $hashedPassword);
                    $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                    $stmt->execute();

                    $message = "Le mot de passe a été réinitialisé. Nouveau mot de passe temporaire : " . $tempPassword;
                    $messageType = "success";
                } catch (PDOException $e) {
                    logError("Erreur lors de la réinitialisation du mot de passe d'un utilisateur", $e);
                    $message = "Une erreur est survenue lors de la réinitialisation du mot de passe.";
                    $messageType = "danger";
                }
                break;

            default:
                $message = "Action non reconnue.";
                $messageType = "warning";
                break;
        }
    }

    $csrfToken = generateCsrfToken();
}

// Récupérer la liste des utilisateurs
$users = getUsers($conn);

// Sécuriser le type de message affiché
$allowedMessageTypes = ['success', 'danger', 'warning', 'info'];
if (!in_array($messageType, $allowedMessageTypes, true)) {
    $messageType = 'info';
}

// Titre de la page
$pageTitle = "AEESGS - Gestion des utilisateurs";
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
    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="assets/css/style.css">

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

        /* Navbar styling */
        .navbar-dark {
            background-color: var(--dark-blue) !important;
        }

        .navbar-dark .navbar-brand,
        .navbar-dark .nav-link {
            color: white !important;
        }

        .navbar-dark .nav-link:hover,
        .navbar-dark .nav-link:focus {
            color: var(--pale-yellow) !important;
        }

        .navbar-dark .nav-link.active {
            color: var(--light-blue) !important;
        }

        .dropdown-item:active {
            background-color: var(--medium-blue);
        }

        /* Card styling */
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(33, 52, 72, 0.1);
        }

        /* Button styling */
        .btn-primary {
            background-color: var(--medium-blue) !important;
            border-color: var(--medium-blue) !important;
        }

        .btn-primary:hover,
        .btn-primary:focus {
            background-color: var(--dark-blue) !important;
            border-color: var(--dark-blue) !important;
        }

        .btn-success {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
        }

        .btn-success:hover {
            background-color: #218838 !important;
            border-color: #1e7e34 !important;
        }

        .btn-danger {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
        }

        .btn-danger:hover {
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
        }

        .btn-warning {
            background-color: var(--pale-yellow) !important;
            border-color: var(--pale-yellow) !important;
            color: var(--dark-blue) !important;
        }

        .btn-warning:hover {
            background-color: #e0e3bd !important;
            border-color: #d4d7b1 !important;
        }

        .btn-info {
            background-color: var(--light-blue) !important;
            border-color: var(--light-blue) !important;
            color: white !important;
        }

        .btn-info:hover {
            background-color: #7fa6b5 !important;
            border-color: #7fa6b5 !important;
        }

        /* Badge colors */
        .bg-danger {
            background-color: #dc3545 !important;
        }

        .bg-info {
            background-color: var(--light-blue) !important;
        }

        .bg-success {
            background-color: var(--medium-blue) !important;
        }

        .bg-secondary {
            background-color: #6c757d !important;
        }

        /* Table styling */
        .table-striped>tbody>tr:nth-of-type(odd) {
            background-color: rgba(148, 180, 193, 0.05) !important;
        }

        .table-hover>tbody>tr:hover {
            background-color: rgba(148, 180, 193, 0.1) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--medium-blue) !important;
            border-color: var(--medium-blue) !important;
            color: white !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--light-blue) !important;
            border-color: var(--light-blue) !important;
            color: white !important;
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

        /* Footer styling */
        footer.bg-dark {
            background-color: var(--dark-blue) !important;
        }

        footer a.text-white:hover {
            color: var(--pale-yellow) !important;
            text-decoration: none;
        }

        /* Text colors */
        .text-primary {
            color: var(--medium-blue) !important;
        }

        .text-secondary {
            color: var(--dark-blue) !important;
            opacity: 0.7;
        }
    </style>
</head>

<body>
    <!-- En-tête -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="fas fa-graduation-cap"></i> <strong style="color: var(--light-beige);">AEESGS</strong> - Administration
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">Utilisateurs</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card"></i> Mon profil</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Contenu principal -->
    <main class="py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-users"></i> Gestion des utilisateurs</h1>
                <a href="add_user.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Ajouter un utilisateur
                </a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                    <?php echo secureData($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom d'utilisateur</th>
                                    <th>Nom</th>
                                    <th>Prénom</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Dernière connexion</th>
                                    <th>Date de création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo (int)$user['id_user']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['prenom']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                                <?php echo htmlspecialchars($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $user['est_actif'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $user['est_actif'] ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : 'Jamais'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['date_creation'])); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <!-- Bouton Modifier -->
                                                <a href="edit_user.php?id=<?php echo $user['id_user']; ?>" class="btn btn-sm btn-primary" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <!-- Bouton Activer/Désactiver -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int)$user['id_user']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo (int)$user['est_actif']; ?>">
                                                    <button type="submit"
                                                        class="btn btn-sm <?php echo $user['est_actif'] ? 'btn-warning' : 'btn-success'; ?>"
                                                        title="<?php echo $user['est_actif'] ? 'Désactiver' : 'Activer'; ?>"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir <?php echo $user['est_actif'] ? 'désactiver' : 'activer'; ?> cet utilisateur ?');">
                                                        <i class="fas <?php echo $user['est_actif'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                    </button>
                                                </form>

                                                <!-- Bouton Réinitialiser mot de passe -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="reset">
                                                    <input type="hidden" name="id" value="<?php echo (int)$user['id_user']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-info"
                                                        title="Réinitialiser mot de passe"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ?');">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                </form>

                                                <!-- Bouton Supprimer (sauf pour l'utilisateur lui-même) -->
                                                <?php if ($user['id_user'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int)$user['id_user']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                            title="Supprimer"
                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row align-items-start">
                <div class="col-md-4 text-center d-flex flex-column justify-content-start">
                    <h5><strong style="color: var(--light-beige);">AEESGS</strong> - Administration</h5>
                    <p>Panneau d'administration pour la gestion des étudiants guinéens au Sénégal.</p>
                </div>
                <div class="col-md-4 text-center d-flex flex-column justify-content-start">
                    <h5>Liens rapides</h5>
                    <ul class="list-unstyled">
                        <li><a href="dashboard.php" class="text-white">Tableau de bord</a></li>
                        <li><a href="users.php" class="text-white">Utilisateurs</a></li>
                        <li><a href="logout.php" class="text-white">Déconnexion</a></li>
                    </ul>
                </div>
                <div class="col-md-4 text-center d-flex flex-column justify-content-start">
                    <h5>Support</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> admin@amea.org</li>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Scripts personnalisés -->
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
                },
                order: [
                    [0, 'desc']
                ], // Trier par ID décroissant par défaut
                pageLength: 10, // Nombre d'éléments par page
                responsive: true
            });
        });
    </script>
</body>

</html>