<?php
// TODO: implementer une reinitialisation de mot de passe oublier
/**
 * Page de connexion administrateur
 * Fichier: login.php
 */

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';

// Initialiser les variables
$error = "";

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation des entrées
    if (empty($username) || empty($password)) {
        $error = "Veuillez entrer un nom d'utilisateur et un mot de passe.";
    } else {
        try {
            // Rechercher l'utilisateur dans la base de données
            $sql = "SELECT * FROM user WHERE username = :username AND est_actif = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Vérifier le mot de passe
                if (password_verify($password, $user['password'])) {
                    // Connexion réussie, enregistrer les informations dans la session
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['prenom'] = $user['prenom'];
                    
                    // Mettre à jour la dernière connexion
                    $updateSql = "UPDATE user SET derniere_connexion = NOW() WHERE id_user = :id_user";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':id_user', $user['id_user']);
                    $updateStmt->execute();
                    
                    // Rediriger vers le tableau de bord
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Nom d'utilisateur ou mot de passe incorrect.";
                }
            } else {
                $error = "Nom d'utilisateur ou mot de passe incorrect.";
            }
        } catch(PDOException $e) {
            $error = "Erreur lors de la tentative de connexion: " . $e->getMessage();
        }
    }
}

// Titre de la page
$pageTitle = "Connexion Administration - AMEA";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMEA - Administration</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Styles personnalisés -->
    <style>
        /* Custom color palette */
        :root {
            --dark-blue: #213448;
            --medium-blue: #547792;
            --light-blue: #94B4C1;
            --light-beige: #ECEFCA;
        }
        
        /* Override Bootstrap colors */
        .bg-primary {
            background-color: var(--medium-blue) !important;
        }
        
        .bg-dark {
            background-color: var(--dark-blue) !important;
        }
        
        .text-primary {
            color: var(--medium-blue) !important;
        }
        
        .btn-primary {
            background-color: var(--medium-blue);
            border-color: var(--medium-blue);
        }
        
        .btn-primary:hover {
            background-color: #456781;
            border-color: #456781;
        }
        
        .btn-light {
            background-color: var(--light-beige);
            border-color: var(--light-beige);
            color: var(--dark-blue);
        }
        
        .btn-light:hover {
            background-color: #dfe1b9;
            border-color: #dfe1b9;
            color: var(--dark-blue);
        }
        
        .card {
            border-color: var(--light-blue);
        }
        
        .bg-light {
            background-color: var(--light-beige) !important;
        }
        
        /* Custom elements */
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .navbar-dark .navbar-nav .nav-link:hover,
        .navbar-dark .navbar-nav .nav-link.active {
            color: var(--light-beige);
        }
        
        footer {
            background-color: var(--dark-blue) !important;
        }
        
        .card-header {
            background-color: var(--medium-blue) !important;
        }
        
        .input-group-text {
            background-color: var(--light-blue);
            border-color: var(--light-blue);
            color: var(--dark-blue);
        }
        
        .form-control:focus {
            border-color: var(--medium-blue);
            box-shadow: 0 0 0 0.25rem rgba(84, 119, 146, 0.25);
        }
        
        a {
            color: var(--medium-blue);
            text-decoration: none;
        }
        
        a:hover {
            color: var(--dark-blue);
        }
        
        footer a.text-white:hover {
            color: var(--light-beige) !important;
            text-decoration: underline;
        }
        
        .alert-info {
            background-color: rgba(148, 180, 193, 0.2);
            border-color: var(--light-blue);
            color: var(--dark-blue);
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-graduation-cap"></i> AMEA
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Accueil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">S'enregistrer</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="login.php">Administration</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Contenu principal -->
    <main class="py-5" style="background-color: #f8f9fa;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow" style="border-color: var(--light-blue);">
                        <div class="card-header text-white text-center">
                            <h2 class="h4 mb-0">Connexion Administration</h2>
                        </div>
                        <div class="card-body p-4">
                            <!-- PHP error message placeholder -->
                            <!-- <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Message d'erreur
                            </div> -->
                            
                            <p class="text-muted mb-4 text-center">Veuillez vous connecter pour accéder au tableau de bord administratif.</p>
                            
                            <form action="" method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nom d'utilisateur</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" placeholder="Entrez votre nom d'utilisateur" required>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="password" class="form-label">Mot de passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Entrez votre mot de passe" required>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Se connecter
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Information sur le compte administrateur initial -->
                    <div class="alert alert-info mt-4" style="background-color: rgba(236, 239, 202, 0.5); border-color: var(--light-beige);">
                        <h5 class="alert-heading" style="color: var(--dark-blue);"><i class="fas fa-info-circle" style="color: var(--medium-blue);"></i> Première connexion?</h5>
                        <p>Si aucun compte administrateur n'a été créé, veuillez exécuter le script d'initialisation pour créer un compte par défaut avec les identifiants suivants :</p>
                        <ul>
                            <li>Nom d'utilisateur : <strong>admin</strong></li>
                            <li>Mot de passe : <strong>admin123</strong></li>
                        </ul>
                        <p class="mb-0">Pensez à changer le mot de passe par défaut après la première connexion.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>AMEA - Association des Étudiants Guinéens au Sénégal</h5>
                    <p>Une plateforme de recensement et de mise en réseau pour tous les étudiants guinéens au Sénégal.</p>
                </div>
                <div class="col-md-3">
                    <h5>Liens rapides</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Accueil</a></li>
                        <li><a href="register.php" class="text-white">S'enregistrer</a></li>
                        <li><a href="login.php" class="text-white">Administration</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> contact@amea.org</li>
                        <li><i class="fas fa-phone me-2"></i> +221 XX XXX XX XX</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; 2025 AMEA. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>