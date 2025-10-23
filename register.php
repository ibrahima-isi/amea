<?php

/**
 * Formulaire d'enregistrement des étudiants
 * Fichier: register.php
 */

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Initialiser les variables
$successMessage = '';
$error = "";
$formData = [];

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "La session a expiré. Veuillez soumettre à nouveau le formulaire.";
    } else {
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

        // Valider le numéro de téléphone
        if (empty($error) && !isValidPhone($formData['telephone'])) {
            $error = "Le numéro de téléphone renseigné n'est pas valide.";
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
                // Vérifier les doublons sur l'adresse email
                $duplicateSql = "SELECT COUNT(*) FROM personnes WHERE email = :email";
                $duplicateStmt = $conn->prepare($duplicateSql);
                $duplicateStmt->bindParam(':email', $formData['email']);
                $duplicateStmt->execute();

                if ($duplicateStmt->fetchColumn() > 0) {
                    $error = "Cette adresse email est déjà enregistrée.";
                } else {
                    $sql = "INSERT INTO personnes (nom, prenom, sexe, age, date_naissance, lieu_residence,
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
                    $successMessage = "Votre enregistrement a été effectué avec succès ! Merci pour votre participation.";

                    // Réinitialiser les données du formulaire après succès
                    $formData = [];
                }
            } catch (PDOException $e) {
                logError("Erreur lors de l'enregistrement d'un étudiant", $e);
                $error = "Une erreur inattendue est survenue lors de l'enregistrement. Veuillez réessayer plus tard.";
            }
        }
    }
}

// Préparer rendu via template
$csrfToken = generateCsrfToken();
$templatePath = __DIR__ . '/templates/register.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

// Construire le bloc de feedback
$feedback = '';
if (!empty($successMessage)) {
    $feedback = '<div class="alert alert-success">'
        . '<i class="fas fa-check-circle"></i> '
        . htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8')
        . '</div>'
        . '<div class="text-center mb-4">'
        . '<a href="register.php" class="btn btn-primary">'
        . '<i class="fas fa-user-plus"></i> Nouvel enregistrement'
        . '</a>'
        . ' '
        . '<a href="index.php" class="btn btn-secondary">'
        . '<i class="fas fa-home"></i> Retour à l\'accueil'
        . '</a>'
        . '</div>';
} elseif (!empty($error)) {
    $feedback = '<div class="alert alert-danger">'
        . '<i class="fas fa-exclamation-triangle"></i> '
        . htmlspecialchars($error, ENT_QUOTES, 'UTF-8')
        . '</div>';
}

// Helper pour attribut selected
$sel = function ($cond) { return $cond ? 'selected' : ''; };

$tpl = file_get_contents($templatePath);

// Header/Footer partials
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = file_get_contents(__DIR__ . '/templates/partials/footer.html');
$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => 'active',
    '{{login_active}}' => '',
]);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{feedback_block}}' => $feedback,
    '{{form_action}}' => htmlspecialchars($_SERVER['PHP_SELF'] ?? 'register.php', ENT_QUOTES, 'UTF-8'),
    '{{csrf_token}}' => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
    '{{nom}}' => htmlspecialchars($formData['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($formData['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{date_naissance}}' => htmlspecialchars($formData['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{telephone}}' => htmlspecialchars($formData['telephone'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{lieu_residence}}' => htmlspecialchars($formData['lieu_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{annee_arrivee}}' => htmlspecialchars($formData['annee_arrivee'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{precision_logement}}' => htmlspecialchars($formData['precision_logement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{etablissement}}' => htmlspecialchars($formData['etablissement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{domaine_etudes}}' => htmlspecialchars($formData['domaine_etudes'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{niveau_etudes}}' => htmlspecialchars($formData['niveau_etudes'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{projet_apres_formation}}' => htmlspecialchars($formData['projet_apres_formation'] ?? '', ENT_QUOTES, 'UTF-8'),
    // Sexe selects
    '{{sexe_sel_none}}' => $sel(empty($formData['sexe'] ?? '')),
    '{{sexe_sel_Masculin}}' => $sel(($formData['sexe'] ?? '') === 'Masculin'),
    '{{sexe_sel_Féminin}}' => $sel(($formData['sexe'] ?? '') === 'Féminin'),
    // Type logement
    '{{type_logement_sel_none}}' => $sel(empty($formData['type_logement'] ?? '')),
    '{{type_logement_sel_En famille}}' => $sel(($formData['type_logement'] ?? '') === 'En famille'),
    '{{type_logement_sel_En colocation}}' => $sel(($formData['type_logement'] ?? '') === 'En colocation'),
    '{{type_logement_sel_En résidence universitaire}}' => $sel(($formData['type_logement'] ?? '') === 'En résidence universitaire'),
    '{{type_logement_sel_Autre}}' => $sel(($formData['type_logement'] ?? '') === 'Autre'),
    // Statut
    '{{statut_sel_none}}' => $sel(empty($formData['statut'] ?? '')),
    '{{statut_sel_Élève}}' => $sel(($formData['statut'] ?? '') === 'Élève'),
    '{{statut_sel_Étudiant}}' => $sel(($formData['statut'] ?? '') === 'Étudiant'),
    '{{statut_sel_Stagiaire}}' => $sel(($formData['statut'] ?? '') === 'Stagiaire'),
]);

echo $output;
?>
