<?php

/**
 * Formulaire d'enregistrement des étudiants
 * Fichier: register.php
 */

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Initialiser les variables
$success = false;
$error = "";
$formData = [];

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les données du formulaire
    $formData = [
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'sexe' => $_POST['sexe'] ?? '',
        'date_naissance' => $_POST['date_naissance'] ?? '',
        'lieu_residence' => trim($_POST['lieu_residence'] ?? ''),
        'etablissement' => trim($_POST['etablissement'] ?? ''),
        'statut' => $_POST['statut'] ?? '',
        'domaine_etudes' => trim($_POST['domaine_etudes'] ?? ''),
        'niveau_etudes' => trim($_POST['niveau_etudes'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'annee_arrivee' => $_POST['annee_arrivee'] ?? null,
        'type_logement' => $_POST['type_logement'] ?? '',
        'precision_logement' => trim($_POST['precision_logement'] ?? ''),
        'projet_apres_formation' => trim($_POST['projet_apres_formation'] ?? '')
    ];

    // Valider les données
    $requiredFields = [
        'nom',
        'prenom',
        'sexe',
        'date_naissance',
        'lieu_residence',
        'etablissement',
        'statut',
        'domaine_etudes',
        'niveau_etudes',
        'telephone',
        'email',
        'type_logement'
    ];

    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $error = "Tous les champs obligatoires doivent être remplis.";
            break;
        }
    }

    // Valider l'email
    if (empty($error) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    }

    // Calculer l'âge à partir de la date de naissance
    if (empty($error) && !empty($formData['date_naissance'])) {
        $dateNaissance = new DateTime($formData['date_naissance']);
        $today = new DateTime();
        $age = $dateNaissance->diff($today)->y;
        $formData['age'] = $age;
    }

    // Si aucune erreur, enregistrer les données dans la base de données
    if (empty($error)) {
        try {
            $sql = "INSERT INTO personne (nom, prenom, sexe, age, date_naissance, lieu_residence, 
                    etablissement, statut, domaine_etudes, niveau_etudes, telephone, email, 
                    annee_arrivee, type_logement, precision_logement, projet_apres_formation) 
                    VALUES (:nom, :prenom, :sexe, :age, :date_naissance, :lieu_residence, 
                    :etablissement, :statut, :domaine_etudes, :niveau_etudes, :telephone, :email, 
                    :annee_arrivee, :type_logement, :precision_logement, :projet_apres_formation)";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':nom', $formData['nom']);
            $stmt->bindParam(':prenom', $formData['prenom']);
            $stmt->bindParam(':sexe', $formData['sexe']);
            $stmt->bindParam(':age', $formData['age']);
            $stmt->bindParam(':date_naissance', $formData['date_naissance']);
            $stmt->bindParam(':lieu_residence', $formData['lieu_residence']);
            $stmt->bindParam(':etablissement', $formData['etablissement']);
            $stmt->bindParam(':statut', $formData['statut']);
            $stmt->bindParam(':domaine_etudes', $formData['domaine_etudes']);
            $stmt->bindParam(':niveau_etudes', $formData['niveau_etudes']);
            $stmt->bindParam(':telephone', $formData['telephone']);
            $stmt->bindParam(':email', $formData['email']);
            $stmt->bindParam(':annee_arrivee', $formData['annee_arrivee']);
            $stmt->bindParam(':type_logement', $formData['type_logement']);
            $stmt->bindParam(':precision_logement', $formData['precision_logement']);
            $stmt->bindParam(':projet_apres_formation', $formData['projet_apres_formation']);

            $stmt->execute();
            $success = true;

            // Réinitialiser les données du formulaire après succès
            $formData = [];
        } catch (PDOException $e) {
            $error = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    }
}
// TODO: INCLURE LA BAR DE NAVIGATION
// FIXME : METTRE LE CSS DANS UN FICHIER SEPARÉ

// Titre de la page
$pageTitle = "Enregistrement - AMEA";
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

        /* Main content styling */
        main {
            background-color: #f8f8fa;
        }

        h2,
        h3,
        h4,
        h5,
        h6 {
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
            padding: 1rem 1.5rem;
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

        .form-label {
            color: var(--dark-blue);
            font-weight: 500;
        }

        /* Section headers */
        .h5 {
            color: var(--medium-blue);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(148, 180, 193, 0.2);
            margin-top: 1rem;
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

        .btn-secondary {
            background-color: var(--dark-blue) !important;
            border-color: var(--dark-blue) !important;
        }

        .btn-secondary:hover,
        .btn-secondary:focus {
            background-color: rgba(33, 52, 72, 0.9) !important;
            border-color: rgba(33, 52, 72, 0.9) !important;
        }

        .btn-outline-secondary {
            color: var(--medium-blue) !important;
            border-color: var(--medium-blue) !important;
        }

        .btn-outline-secondary:hover {
            background-color: var(--medium-blue) !important;
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

        .text-muted {
            color: var(--dark-blue) !important;
            opacity: 0.6;
        }

        /* Required fields */
        .text-danger {
            color: #dc3545 !important;
        }
    </style>
</head>
<body>
    <!-- Contenu principal -->
    <main class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div class="card shadow">
                        <div class="card-header">
                            <h2 class="h4 mb-0">Formulaire d'enregistrement des étudiants guinéens au Sénégal</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Votre enregistrement a été effectué avec succès ! Merci pour votre participation.
                                </div>
                                <div class="text-center mb-4">
                                    <a href="register.php" class="btn btn-primary">
                                        <i class="fas fa-user-plus"></i> Nouvel enregistrement
                                    </a>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-home"></i> Retour à l'accueil
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>

                                <p class="text-muted mb-4">Veuillez remplir ce formulaire pour vous enregistrer dans la base de données des étudiants guinéens au Sénégal.</p>

                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="registrationForm">
                                    <!-- Informations personnelles -->
                                    <h3 class="h5 mb-3">Informations personnelles</h3>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($formData['nom'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($formData['prenom'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                                            <select class="form-select" id="sexe" name="sexe" required>
                                                <option value="" disabled <?php echo empty($formData['sexe']) ? 'selected' : ''; ?>>Sélectionnez</option>
                                                <option value="Masculin" <?php echo isset($formData['sexe']) && $formData['sexe'] == 'Masculin' ? 'selected' : ''; ?>>Masculin</option>
                                                <option value="Féminin" <?php echo isset($formData['sexe']) && $formData['sexe'] == 'Féminin' ? 'selected' : ''; ?>>Féminin</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($formData['date_naissance'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <!-- Informations de contact et résidence -->
                                    <h3 class="h5 mb-3">Contact et résidence</h3>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo htmlspecialchars($formData['telephone'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lieu_residence" class="form-label">Lieu de résidence au Sénégal <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="lieu_residence" name="lieu_residence" value="<?php echo htmlspecialchars($formData['lieu_residence'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="annee_arrivee" class="form-label">Année d'arrivée au Sénégal</label>
                                            <input type="number" class="form-control" id="annee_arrivee" name="annee_arrivee" min="2000" max="2030" value="<?php echo htmlspecialchars($formData['annee_arrivee'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="type_logement" class="form-label">Type de logement <span class="text-danger">*</span></label>
                                            <select class="form-select" id="type_logement" name="type_logement" required>
                                                <option value="" disabled <?php echo empty($formData['type_logement']) ? 'selected' : ''; ?>>Sélectionnez</option>
                                                <option value="En famille" <?php echo isset($formData['type_logement']) && $formData['type_logement'] == 'En famille' ? 'selected' : ''; ?>>En famille</option>
                                                <option value="En colocation" <?php echo isset($formData['type_logement']) && $formData['type_logement'] == 'En colocation' ? 'selected' : ''; ?>>En colocation</option>
                                                <option value="En résidence universitaire" <?php echo isset($formData['type_logement']) && $formData['type_logement'] == 'En résidence universitaire' ? 'selected' : ''; ?>>En résidence universitaire</option>
                                                <option value="Autre" <?php echo isset($formData['type_logement']) && $formData['type_logement'] == 'Autre' ? 'selected' : ''; ?>>Autre</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="precision_logement" class="form-label">Précisions sur le logement</label>
                                            <input type="text" class="form-control" id="precision_logement" name="precision_logement" value="<?php echo htmlspecialchars($formData['precision_logement'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <!-- Informations académiques -->
                                    <h3 class="h5 mb-3">Informations académiques</h3>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label for="etablissement" class="form-label">Établissement <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="etablissement" name="etablissement" value="<?php echo htmlspecialchars($formData['etablissement'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="statut" class="form-label">Statut <span class="text-danger">*</span></label>
                                            <select class="form-select" id="statut" name="statut" required>
                                                <option value="" disabled <?php echo empty($formData['statut']) ? 'selected' : ''; ?>>Sélectionnez</option>
                                                <option value="Élève" <?php echo isset($formData['statut']) && $formData['statut'] == 'Élève' ? 'selected' : ''; ?>>Élève</option>
                                                <option value="Étudiant" <?php echo isset($formData['statut']) && $formData['statut'] == 'Étudiant' ? 'selected' : ''; ?>>Étudiant</option>
                                                <option value="Stagiaire" <?php echo isset($formData['statut']) && $formData['statut'] == 'Stagiaire' ? 'selected' : ''; ?>>Stagiaire</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="domaine_etudes" class="form-label">Domaine d'études <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="domaine_etudes" name="domaine_etudes" value="<?php echo htmlspecialchars($formData['domaine_etudes'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="niveau_etudes" class="form-label">Niveau d'études <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="niveau_etudes" name="niveau_etudes" value="<?php echo htmlspecialchars($formData['niveau_etudes'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <!-- Projets futurs -->
                                    <h3 class="h5 mb-3">Projets futurs</h3>
                                    <div class="mb-4">
                                        <label for="projet_apres_formation" class="form-label">Projet après formation</label>
                                        <textarea class="form-control" id="projet_apres_formation" name="projet_apres_formation" rows="3"><?php echo htmlspecialchars($formData['projet_apres_formation'] ?? ''); ?></textarea>
                                    </div>

                                    <!-- Boutons de soumission -->
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="reset" class="btn btn-outline-secondary">
                                            <i class="fas fa-undo"></i> Réinitialiser
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Enregistrer
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Pied de page -->
    <footer class="bg-dark text-white py-4 mt-5">
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
                <p>&copy; <?php echo date('Y'); ?> AMEA. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personnalisés -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/validation.js"></script>
</body>

</html>