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
$errors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors']);
unset($_SESSION['form_data']);

// Traitement du formulaire lors de la soumission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: register.php');
        exit();
    }

    // 1. Sanitize and retrieve form data
    $formData = [
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'numero_identite' => trim($_POST['numero_identite'] ?? ''),
        'sexe' => $_POST['sexe'] ?? '',
        'date_naissance' => $_POST['date_naissance'] ?? '',
        'lieu_residence' => trim($_POST['lieu_residence'] ?? ''),
        'autre_lieu_residence' => trim($_POST['autre_lieu_residence'] ?? ''),
        'etablissement' => trim($_POST['etablissement'] ?? ''),
        'autre_etablissement' => trim($_POST['autre_etablissement'] ?? ''),
        'statut' => $_POST['statut'] ?? '',
        'domaine_etudes' => trim($_POST['domaine_etudes'] ?? ''),
        'autre_domaine_etudes' => trim($_POST['autre_domaine_etudes'] ?? ''),
        'niveau_etudes' => trim($_POST['niveau_etudes'] ?? ''),
        'autre_niveau_etudes' => trim($_POST['autre_niveau_etudes'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'nationalites' => $_POST['nationalites'] ?? '',
        'annee_arrivee' => $_POST['annee_arrivee'] ?? null,
        'type_logement' => $_POST['type_logement'] ?? '',
        'precision_logement' => trim($_POST['precision_logement'] ?? ''),
        'projet_apres_formation' => trim($_POST['projet_apres_formation'] ?? ''),
        'cv_path' => null // Initialize cv_path
    ];

    // 2. Handle 'Other' options
    if ($formData['lieu_residence'] === 'Autre') $formData['lieu_residence'] = $formData['autre_lieu_residence'];
    if ($formData['etablissement'] === 'Autre') $formData['etablissement'] = $formData['autre_etablissement'];
    if ($formData['domaine_etudes'] === 'Autre') $formData['domaine_etudes'] = $formData['autre_domaine_etudes'];
    if ($formData['niveau_etudes'] === 'Autre') $formData['niveau_etudes'] = $formData['autre_niveau_etudes'];

    // 3. Normalize nullable fields and calculate age
    $formData['annee_arrivee'] = ($formData['annee_arrivee'] === null || $formData['annee_arrivee'] === '') ? null : (int)$formData['annee_arrivee'];
    $formData['precision_logement'] = $formData['precision_logement'] === '' ? null : $formData['precision_logement'];
    $formData['projet_apres_formation'] = $formData['projet_apres_formation'] === '' ? null : $formData['projet_apres_formation'];
    $formData['age'] = null;

    // Process Nationalities (Tagify returns JSON: [{"value":"Mali"}, ...])
    $nationalites = null;
    if (!empty($formData['nationalites'])) {
        $decoded = json_decode($formData['nationalites'], true);
        if (is_array($decoded)) {
            $nationalitesList = array_map(function($item) {
                return $item['value'];
            }, $decoded);
            $nationalites = json_encode($nationalitesList, JSON_UNESCAPED_UNICODE);
        }
    }
    $formData['nationalites_json'] = $nationalites;

    // 4. Validation
    $errors = [];
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
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE numero_identite = ?");
        $stmt->execute([$formData['numero_identite']]);
        if ($stmt->fetch()) {
            $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';
        }
    }

    if (!isset($errors['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'adresse email n'est pas valide.";
    }

    if (!isset($errors['telephone']) && !isValidPhone($formData['telephone'])) {
        $errors['telephone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    }

    if (empty($errors['date_naissance'])) {
        $formData['age'] = calculateAge($formData['date_naissance']);
        if ($formData['age'] < 15) {
            $errors['date_naissance'] = "L'âge doit être d'au moins 15 ans.";
        }
    }

    // 5. Handle file uploads (Identité and CV)
    $identitePath = null;
    $identiteUploadResult = handleFileUpload($_FILES['photo'] ?? [], ['jpg', 'jpeg', 'png', 'gif', 'pdf'], 2 * 1024 * 1024, 'uploads/students');
    if (!$identiteUploadResult['success']) {
        if ($identiteUploadResult['filepath'] !== null) { // Only set error if a file was actually attempted to be uploaded
             $errors['identite'] = $identiteUploadResult['message'];
        }
    } else {
        $identitePath = $identiteUploadResult['filepath'];
    }
    $formData['identite'] = $identitePath;

    $cvPath = null;
    $cvUploadResult = handleFileUpload($_FILES['cv_file'] ?? [], ['pdf', 'png'], 5 * 1024 * 1024, 'uploads/students/cvs');
    if (!$cvUploadResult['success']) {
        if ($cvUploadResult['filepath'] !== null) { // Only set error if a file was actually attempted to be uploaded
             $errors['cv'] = $cvUploadResult['message'];
        }
    } else {
        $cvPath = $cvUploadResult['filepath'];
    }
    $formData['cv_path'] = $cvPath;

    // 6. If validation fails, redirect back
    if (!empty($errors)) {
        $_SESSION['form_data'] = $formData;
        $_SESSION['form_errors'] = $errors;
        header('Location: register.php');
        exit();
    }

    // 7. If validation passes, insert into DB
    if (!empty($formData['etablissement'])) {
        $stmt = $conn->prepare("SELECT id FROM etablissements WHERE nom = ?");
        $stmt->execute([$formData['etablissement']]);
        if ($stmt->fetchColumn() === false) {
            $conn->prepare("INSERT INTO etablissements (nom) VALUES (?)")->execute([$formData['etablissement']]);
        }
    }
    if (!empty($formData['domaine_etudes'])) {
        $stmt = $conn->prepare("SELECT id FROM domaines_etudes WHERE nom = ?");
        $stmt->execute([$formData['domaine_etudes']]);
        if ($stmt->fetchColumn() === false) {
            $conn->prepare("INSERT INTO domaines_etudes (nom) VALUES (?)")->execute([$formData['domaine_etudes']]);
        }
    }
    if (!empty($formData['niveau_etudes'])) {
        $stmt = $conn->prepare("SELECT id FROM niveaux_etudes WHERE nom = ?");
        $stmt->execute([$formData['niveau_etudes']]);
        if ($stmt->fetchColumn() === false) {
            $conn->prepare("INSERT INTO niveaux_etudes (nom) VALUES (?)")->execute([$formData['niveau_etudes']]);
        }
    }

    try {
        $duplicateSql = "SELECT COUNT(*) FROM personnes WHERE email = :email";
        $duplicateStmt = $conn->prepare($duplicateSql);
        $duplicateStmt->bindParam(':email', $formData['email']);
        $duplicateStmt->execute();
        if ($duplicateStmt->fetchColumn() > 0) {
            $errors['email'] = "Cette adresse email est déjà enregistrée.";
            $_SESSION['form_data'] = $formData;
            $_SESSION['form_errors'] = $errors;
            setFlashMessage('error', 'Cette adresse email est déjà enregistrée.');
            header('Location: register.php');
            exit();
        }

        $phoneSql = "SELECT COUNT(*) FROM personnes WHERE telephone = :telephone";
        $phoneStmt = $conn->prepare($phoneSql);
        $phoneStmt->bindParam(':telephone', $formData['telephone']);
        $phoneStmt->execute();
        if ($phoneStmt->fetchColumn() > 0) {
            $errors['telephone'] = "Ce numéro de téléphone est déjà enregistré.";
            $_SESSION['form_data'] = $formData;
            $_SESSION['form_errors'] = $errors;
            setFlashMessage('error', 'Ce numéro de téléphone est déjà enregistré.');
            header('Location: register.php');
            exit();
        }

        $sql = "INSERT INTO personnes (nom, prenom, numero_identite, sexe, age, date_naissance, lieu_residence,
                etablissement, statut, domaine_etudes, niveau_etudes, telephone, email,
                annee_arrivee, type_logement, precision_logement, projet_apres_formation, identite, nationalites, cv_path)
                VALUES (:nom, :prenom, :numero_identite, :sexe, :age, :date_naissance, :lieu_residence,
                :etablissement, :statut, :domaine_etudes, :niveau_etudes, :telephone, :email,
                :annee_arrivee, :type_logement, :precision_logement, :projet_apres_formation, :identite, :nationalites, :cv_path)";

        $stmt = $conn->prepare($sql);

        $bindings = [
            ':nom' => $formData['nom'],
            ':prenom' => $formData['prenom'],
            ':numero_identite' => $formData['numero_identite'],
            ':sexe' => $formData['sexe'],
            ':age' => $formData['age'],
            ':date_naissance' => $formData['date_naissance'],
            ':lieu_residence' => $formData['lieu_residence'],
            ':etablissement' => $formData['etablissement'],
            ':statut' => $formData['statut'],
            ':domaine_etudes' => $formData['domaine_etudes'],
            ':niveau_etudes' => $formData['niveau_etudes'],
            ':telephone' => $formData['telephone'],
            ':email' => $formData['email'],
            ':annee_arrivee' => $formData['annee_arrivee'],
            ':type_logement' => $formData['type_logement'],
            ':precision_logement' => $formData['precision_logement'],
            ':projet_apres_formation' => $formData['projet_apres_formation'],
            ':identite' => $formData['identite'],
            ':nationalites' => $formData['nationalites_json'],
            ':cv_path' => $formData['cv_path'],
        ];

        $stmt->execute($bindings);
        
        // Retrieve the last inserted ID
        $newStudentId = $conn->lastInsertId();
        
        // Set session variable for the details page
        $_SESSION['registration_student_id'] = $newStudentId;
        
        setFlashMessage('success', 'Vous êtes inscrit avec succès');
        session_write_close();
        header('Location: registration-details.php');
        exit();

    } catch (PDOException $e) {
        // Affiche l'erreur pour le débogage
        echo "<h1>Erreur de base de données :</h1>";
        echo "<pre>";
        print_r($e->getMessage());
        echo "</pre>";
        // Affiche les données qui ont été envoyées
        echo "<h2>Données envoyées :</h2>";
        echo "<pre>";
        print_r($bindings);
        echo "</pre>";
        exit(); // Arrête l'exécution pour ne pas rediriger
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

// Rendu du template
$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$feedback = '';
if (!empty($errors)) {
    // This is now handled by the SweetAlert flash message
}

$tpl = file_get_contents($templatePath);

// Header/Footer partials
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
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

// Fetch locations from the database
$stmt = $conn->query("SELECT region, name FROM locations ORDER BY CASE WHEN region LIKE 'Dakar%' THEN 0 ELSE 1 END, region ASC, name ASC");
$locations = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

$lieuResidenceOptions = '<option value="" selected>Sélectionnez un lieu</option>';
foreach ($locations as $region => $cities) {
    $lieuResidenceOptions .= "<optgroup label=\"$region\">";
    foreach ($cities as $city) {
        $selected = ($formData['lieu_residence'] ?? '') === $city ? 'selected' : '';
        $lieuResidenceOptions .= "<option value=\"$city\" $selected>$city</option>";
    }
    $lieuResidenceOptions .= "</optgroup>";
}
$lieuResidenceOptions .= '<option value="Autre">Autre</option>';

$anneeArriveeOptions = '<option value="" selected>Sélectionnez</option>';
$currentYear = date('Y');
for ($year = $currentYear; $year >= 1990; $year--) {
    $selected = ($formData['annee_arrivee'] ?? '') == $year ? 'selected' : '';
    $anneeArriveeOptions .= "<option value=\"$year\" $selected>$year</option>";
}

$maxBirthDate = ($currentYear - 15) . '-12-31';

$sel = fn($value, $option) => $value === $option ? 'selected' : '';
$checked = fn($value, $option) => $value === $option ? 'checked' : '';

$validation_errors_json = '';
if (!empty($errors)) {
    $validation_errors_json = json_encode($errors);
}

$replacements = [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{flash_json}}' => $flash_json,
    '{{validation_errors_json}}' => $validation_errors_json,
    '{{feedback_block}}' => $feedback,
    '{{form_action}}' => htmlspecialchars($_SERVER['PHP_SELF'] ?? 'register.php', ENT_QUOTES, 'UTF-8'),
    '{{csrf_token}}' => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
    '{{nom}}' => htmlspecialchars($formData['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($formData['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{numero_identite}}' => htmlspecialchars($formData['numero_identite'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{date_naissance}}' => htmlspecialchars($formData['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{max_birth_date}}' => $maxBirthDate,
    '{{telephone}}' => htmlspecialchars($formData['telephone'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{lieu_residence}}' => htmlspecialchars($formData['lieu_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{lieu_residence_options}}' => $lieuResidenceOptions,
    '{{annee_arrivee_options}}' => $anneeArriveeOptions,
    '{{precision_logement}}' => htmlspecialchars($formData['precision_logement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{etablissement_options}}' => $etablissementOptions,
    '{{domaine_etudes_options}}' => $domaineOptions,
    '{{niveau_etudes_options}}' => $niveauOptions,
    '{{projet_apres_formation}}' => htmlspecialchars($formData['projet_apres_formation'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{sexe_checked_Masculin}}' => $checked($formData['sexe'] ?? '', 'Masculin'),
    '{{sexe_checked_Feminin}}' => ($formData['sexe'] ?? '') === 'Féminin' ? 'selected' : '',
    '{{type_logement_sel_none}}' => empty($formData['type_logement'] ?? '') ? 'selected' : '',
    '{{type_logement_sel_Colocation}}' => ($formData['type_logement'] ?? '') === 'Colocation' ? 'selected' : '',
    '{{type_logement_sel_Famille}}' => ($formData['type_logement'] ?? '') === 'Famille' ? 'selected' : '',
    '{{type_logement_sel_Hébergement temporaire}}' => ($formData['type_logement'] ?? '') === 'Hébergement temporaire' ? 'selected' : '',
    '{{type_logement_sel_Location}}' => ($formData['type_logement'] ?? '') === 'Location' ? 'selected' : '',
    '{{type_logement_sel_Résidence universitaire}}' => ($formData['type_logement'] ?? '') === 'Résidence universitaire' ? 'selected' : '',
    '{{type_logement_sel_Autre}}' => ($formData['type_logement'] ?? '') === 'Autre' ? 'selected' : '',
    '{{statut_sel_none}}' => empty($formData['statut'] ?? '') ? 'selected' : '',
    '{{statut_sel_Élève}}' => ($formData['statut'] ?? '') === 'Élève' ? 'selected' : '',
    '{{statut_sel_Étudiant}}' => ($formData['statut'] ?? '') === 'Étudiant' ? 'selected' : '',
    '{{statut_sel_Stagiaire}}' => ($formData['statut'] ?? '') === 'Stagiaire' ? 'selected' : '',
    '{{error_nom}}' => $errors['nom'] ?? '',
    '{{error_prenom}}' => $errors['prenom'] ?? '',
    '{{error_numero_identite}}' => $errors['numero_identite'] ?? '',
    '{{error_sexe}}' => $errors['sexe'] ?? '',
    '{{error_date_naissance}}' => $errors['date_naissance'] ?? '',
    '{{error_photo}}' => $errors['identite'] ?? '',
    '{{error_telephone}}' => $errors['telephone'] ?? '',
    '{{error_email}}' => $errors['email'] ?? '',
    '{{error_lieu_residence}}' => $errors['lieu_residence'] ?? '',
    '{{error_etablissement}}' => $errors['etablissement'] ?? '',
    '{{error_statut}}' => $errors['statut'] ?? '',
    '{{error_domaine_etudes}}' => $errors['domaine_etudes'] ?? '',
    '{{error_niveau_etudes}}' => $errors['niveau_etudes'] ?? '',
    '{{error_type_logement}}' => $errors['type_logement'] ?? '',
    '{{is_invalid_nom}}' => isset($errors['nom']) ? 'is-invalid' : '',
    '{{is_invalid_prenom}}' => isset($errors['prenom']) ? 'is-invalid' : '',
    '{{is_invalid_numero_identite}}' => isset($errors['numero_identite']) ? 'is-invalid' : '',
    '{{is_invalid_sexe}}' => isset($errors['sexe']) ? 'is-invalid' : '',
    '{{is_invalid_date_naissance}}' => isset($errors['date_naissance']) ? 'is-invalid' : '',
    '{{is_invalid_photo}}' => isset($errors['identite']) ? 'is-invalid' : '',
    '{{is_invalid_telephone}}' => isset($errors['telephone']) ? 'is-invalid' : '',
    '{{is_invalid_email}}' => isset($errors['email']) ? 'is-invalid' : '',
    '{{is_invalid_lieu_residence}}' => isset($errors['lieu_residence']) ? 'is-invalid' : '',
    '{{is_invalid_etablissement}}' => isset($errors['etablissement']) ? 'is-invalid' : '',
    '{{is_invalid_statut}}' => isset($errors['statut']) ? 'is-invalid' : '',
    '{{is_invalid_domaine_etudes}}' => isset($errors['domaine_etudes']) ? 'is-invalid' : '',
    '{{is_invalid_niveau_etudes}}' => isset($errors['niveau_etudes']) ? 'is-invalid' : '',
    '{{is_invalid_type_logement}}' => isset($errors['type_logement']) ? 'is-invalid' : '',
    '{{error_cv}}' => $errors['cv'] ?? '', // Add this line
    '{{is_invalid_cv}}' => isset($errors['cv']) ? 'is-invalid' : '', // Add this line
];

$output = strtr($tpl, $replacements);

echo $output;
?>

