<?php

/**
 * Formulaire d'enregistrement des étudiants
 * Fichier: register.php
 */

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

require_once 'config/session.php';

// Initialiser les variables
$errors = [];
$formData = [];

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['form'] = "La session a expiré. Veuillez soumettre à nouveau le formulaire.";
    } else {
        // Récupérer les données du formulaire
        $formData = [
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
            'numero_identite' => trim($_POST['numero_identite'] ?? ''),
            'sexe' => $_POST['sexe'] ?? '',
            'date_naissance' => $_POST['date_naissance'] ?? '',
            'lieu_residence' => trim($_POST['lieu_residence'] ?? ''),
            'etablissement' => trim($_POST['etablissement'] ?? ''),
            'autre_etablissement' => trim($_POST['autre_etablissement'] ?? ''),
            'statut' => $_POST['statut'] ?? '',
            'domaine_etudes' => trim($_POST['domaine_etudes'] ?? ''),
            'autre_domaine_etudes' => trim($_POST['autre_domaine_etudes'] ?? ''),
            'niveau_etudes' => trim($_POST['niveau_etudes'] ?? ''),
            'autre_niveau_etudes' => trim($_POST['autre_niveau_etudes'] ?? ''),
            'telephone' => trim($_POST['telephone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'annee_arrivee' => $_POST['annee_arrivee'] ?? null,
            'type_logement' => $_POST['type_logement'] ?? '',
            'precision_logement' => trim($_POST['precision_logement'] ?? ''),
            'projet_apres_formation' => trim($_POST['projet_apres_formation'] ?? '')
        ];

        // Valider les données
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoTmpPath = $_FILES['photo']['tmp_name'];
            $photoName = $_FILES['photo']['name'];
            $photoSize = $_FILES['photo']['size'];
            $photoExtension = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($photoExtension, $allowedExtensions)) {
                if ($photoSize < 2000000) { // 2MB
                    $newFileName = uniqid('', true) . '.' . $photoExtension;
                    $uploadPath = 'uploads/students/' . $newFileName;
                    if (move_uploaded_file($photoTmpPath, $uploadPath)) {
                        $photoPath = $uploadPath;
                    } else {
                        $errors['photo'] = "Erreur lors de l'upload de l'image.";
                    }
                } else {
                    $errors['photo'] = "L'image est trop volumineuse (max 2MB).";
                }
            } else {
                $errors['photo'] = "Le format de l'image n'est pas supporté (jpg, jpeg, png, gif).";
            }
        }
        $formData['photo'] = $photoPath;

        if ($formData['etablissement'] === 'Autre') {
            $formData['etablissement'] = $formData['autre_etablissement'];
        }

        if ($formData['domaine_etudes'] === 'Autre') {
            $formData['domaine_etudes'] = $formData['autre_domaine_etudes'];
        }

        if ($formData['niveau_etudes'] === 'Autre') {
            $formData['niveau_etudes'] = $formData['autre_niveau_etudes'];
        }

        $requiredFields = [
            'nom' => 'Le nom est requis.',
            'prenom' => 'Le prénom est requis.',
            'sexe' => 'Le sexe est requis.',
            'date_naissance' => 'La date de naissance est requise.',
            'lieu_residence' => 'Le lieu de résidence est requis.',
            'etablissement' => 'L\'établissement est requis.',
            'statut' => 'Le statut est requis.',
            'domaine_etudes' => 'Le domaine d\'études est requis.',
            'niveau_etudes' => 'Le niveau d\'études est requis.',
            'telephone' => 'Le téléphone est requis.',
            'email' => 'L\'email est requis.',
            'type_logement' => 'Le type de logement est requis.'
        ];

        foreach ($requiredFields as $field => $message) {
            if (empty($formData[$field])) {
                $errors[$field] = $message;
            }
        }

        if (empty($formData['numero_identite'])) {
            $errors['numero_identite'] = 'Le numéro d\'identité est requis.';
        } else {
            // Check for uniqueness
            $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE numero_identite = ?");
            $stmt->execute([$formData['numero_identite']]);
            if ($stmt->fetch()) {
                $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';
            }
        }

        // Valider l'email
        if (!isset($errors['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "L'adresse email n'est pas valide.";
        }

        // Valider le numéro de téléphone
        if (!isset($errors['telephone']) && !isValidPhone($formData['telephone'])) {
            $errors['telephone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
        }

        // Calculer l'âge à partir de la date de naissance
        if (empty($errors) && !empty($formData['date_naissance'])) {
            $dateNaissance = new DateTime($formData['date_naissance']);
            $today = new DateTime();
            $age = $dateNaissance->diff($today)->y;
            $formData['age'] = $age;
        }

        // Si aucune erreur, enregistrer les données dans la base de données
        if (empty($errors)) {
            // Add the school to the database if it doesn't exist
            if (!empty($formData['etablissement'])) {
                $stmt = $conn->prepare("SELECT id FROM etablissements WHERE nom = ?");
                $stmt->execute([$formData['etablissement']]);
                if ($stmt->fetchColumn() === false) {
                    $insertStmt = $conn->prepare("INSERT INTO etablissements (nom) VALUES (?)");
                    $insertStmt->execute([$formData['etablissement']]);
                }
            }

            // Add the field of study to the database if it doesn't exist
            if (!empty($formData['domaine_etudes'])) {
                $stmt = $conn->prepare("SELECT id FROM domaines_etudes WHERE nom = ?");
                $stmt->execute([$formData['domaine_etudes']]);
                if ($stmt->fetchColumn() === false) {
                    $insertStmt = $conn->prepare("INSERT INTO domaines_etudes (nom) VALUES (?)");
                    $insertStmt->execute([$formData['domaine_etudes']]);
                }
            }

            // Add the level of study to the database if it doesn't exist
            if (!empty($formData['niveau_etudes'])) {
                $stmt = $conn->prepare("SELECT id FROM niveaux_etudes WHERE nom = ?");
                $stmt->execute([$formData['niveau_etudes']]);
                if ($stmt->fetchColumn() === false) {
                    $insertStmt = $conn->prepare("INSERT INTO niveaux_etudes (nom) VALUES (?)");
                    $insertStmt->execute([$formData['niveau_etudes']]);
                }
            }

            try {
                // Vérifier les doublons sur l'adresse email
                $duplicateSql = "SELECT COUNT(*) FROM personnes WHERE email = :email";
                $duplicateStmt = $conn->prepare($duplicateSql);
                $duplicateStmt->bindParam(':email', $formData['email']);
                $duplicateStmt->execute();

                if ($duplicateStmt->fetchColumn() > 0) {
                    $errors['email'] = "Cette adresse email est déjà enregistrée.";
                } else {
                    $sql = "INSERT INTO personnes (nom, prenom, numero_identite, sexe, age, date_naissance, lieu_residence,
                            etablissement, statut, domaine_etudes, niveau_etudes, telephone, email,
                            annee_arrivee, type_logement, precision_logement, projet_apres_formation, photo)
                            VALUES (:nom, :prenom, :numero_identite, :sexe, :age, :date_naissance, :lieu_residence,
                            :etablissement, :statut, :domaine_etudes, :niveau_etudes, :telephone, :email,
                            :annee_arrivee, :type_logement, :precision_logement, :projet_apres_formation, :photo)";

                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':nom', $formData['nom']);
                    $stmt->bindParam(':prenom', $formData['prenom']);
                    $stmt->bindParam(':numero_identite', $formData['numero_identite']);
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
                    $stmt->bindParam(':photo', $formData['photo']);

                    $stmt->execute();
                    $_SESSION['success_message'] = "Votre enregistrement a été effectué avec succès ! Merci pour votre participation.";
                    header('Location: index.php');
                    exit();
                }
            } catch (PDOException $e) {
                logError("Erreur lors de l'enregistrement d'un étudiant", $e);
                $errors['form'] = "Une erreur inattendue est survenue lors de l'enregistrement. Veuillez réessayer plus tard.";
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

// Fetch schools from the database
$stmt = $conn->query("SELECT nom FROM etablissements ORDER BY nom ASC");
$schools = $stmt->fetchAll(PDO::FETCH_COLUMN);
$schools[] = 'Autre';

// Fetch fields of study from the database
$stmt = $conn->query("SELECT nom FROM domaines_etudes ORDER BY nom ASC");
$domaines = $stmt->fetchAll(PDO::FETCH_COLUMN);
$domaines[] = 'Autre';

// Fetch levels of study from the database
$stmt = $conn->query("SELECT nom FROM niveaux_etudes ORDER BY nom ASC");
$niveaux = $stmt->fetchAll(PDO::FETCH_COLUMN);
$niveaux[] = 'Autre';

// Construire le bloc de feedback
$feedback = '';
if (!empty($errors['form'])) {
    $feedback = '<div class="alert alert-danger">'
        . '<i class="fas fa-exclamation-triangle"></i> '
        . htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8')
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

$etablissementOptions = '';
foreach ($schools as $school) {
    $selected = ($formData['etablissement'] ?? '') === $school ? 'selected' : '';
    $etablissementOptions .= "<option value=\"$school\" $selected>$school</option>";
}

$domaineOptions = '';
foreach ($domaines as $domaine) {
    $selected = ($formData['domaine_etudes'] ?? '') === $domaine ? 'selected' : '';
    $domaineOptions .= "<option value=\"$domaine\" $selected>$domaine</option>";
}

$niveauOptions = '';
foreach ($niveaux as $niveau) {
    $selected = ($formData['niveau_etudes'] ?? '') === $niveau ? 'selected' : '';
    $niveauOptions .= "<option value=\"$niveau\" $selected>$niveau</option>";
}

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{feedback_block}}' => $feedback,
    '{{form_action}}' => htmlspecialchars($_SERVER['PHP_SELF'] ?? 'register.php', ENT_QUOTES, 'UTF-8'),
    '{{csrf_token}}' => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
    '{{nom}}' => htmlspecialchars($formData['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($formData['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{numero_identite}}' => htmlspecialchars($formData['numero_identite'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{date_naissance}}' => htmlspecialchars($formData['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{telephone}}' => htmlspecialchars($formData['telephone'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{lieu_residence}}' => htmlspecialchars($formData['lieu_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{annee_arrivee}}' => htmlspecialchars($formData['annee_arrivee'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{precision_logement}}' => htmlspecialchars($formData['precision_logement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{etablissement_options}}' => $etablissementOptions,
    '{{domaine_etudes_options}}' => $domaineOptions,
    '{{niveau_etudes_options}}' => $niveauOptions,
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
    // Errors
    '{{error_nom}}' => $errors['nom'] ?? '',
    '{{error_prenom}}' => $errors['prenom'] ?? '',
    '{{error_numero_identite}}' => $errors['numero_identite'] ?? '',
    '{{error_sexe}}' => $errors['sexe'] ?? '',
    '{{error_date_naissance}}' => $errors['date_naissance'] ?? '',
    '{{error_photo}}' => $errors['photo'] ?? '',
    '{{error_telephone}}' => $errors['telephone'] ?? '',
    '{{error_email}}' => $errors['email'] ?? '',
    '{{error_lieu_residence}}' => $errors['lieu_residence'] ?? '',
    '{{error_etablissement}}' => $errors['etablissement'] ?? '',
    '{{error_statut}}' => $errors['statut'] ?? '',
    '{{error_domaine_etudes}}' => $errors['domaine_etudes'] ?? '',
    '{{error_niveau_etudes}}' => $errors['niveau_etudes'] ?? '',
    '{{error_type_logement}}' => $errors['type_logement'] ?? '',
]);

echo $output;
?>
