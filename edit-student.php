<?php

/**
 * Edit student page.
 * File: edit-student.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Authentication and Authorization
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get student ID from URL
$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    header('Location: students.php');
    exit();
}

// Fetch student data from the database
$stmt = $conn->prepare("SELECT * FROM personnes WHERE id_personne = ?");
$stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        setFlashMessage('error', 'Étudiant introuvable.');
        header('Location: students.php');
        exit();
    }

    // Fetch nationalities from pivot table
    $stmtNats = $conn->prepare("SELECT p.nom_fr FROM pays p JOIN personne_pays pp ON p.id_pays = pp.id_pays WHERE pp.id_personne = ?");
    $stmtNats->execute([$student_id]);
    $nationalitiesList = $stmtNats->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($nationalitiesList)) {
        $nationalites_value = json_encode($nationalitiesList, JSON_UNESCAPED_UNICODE);
    } else {
        // Fallback to legacy
        $nationalites_value = $student['nationalites'] ?? '';
        if (is_null($nationalites_value)) $nationalites_value = '';
    }

// Fetch data for dropdowns
$stmt = $conn->query("SELECT nom FROM etablissements ORDER BY nom ASC");
$schools = $stmt->fetchAll(PDO::FETCH_COLUMN);
$schools[] = 'Autre';

$stmt = $conn->query("SELECT nom FROM domaines_etudes ORDER BY nom ASC");
$domaines = $stmt->fetchAll(PDO::FETCH_COLUMN);
$domaines[] = 'Autre';

$stmt = $conn->query("SELECT nom FROM niveaux_etudes ORDER BY nom ASC");
$niveaux = $stmt->fetchAll(PDO::FETCH_COLUMN);
$niveaux[] = 'Autre';

// Initialize variables
$errors = [];
$formData = $student; // Pre-fill form with existing student data

// Special handling for 'Autre' fields to correctly display the form
// Check if current values are in the predefined lists for dropdowns.
// If not, it means it's a custom 'Autre' value.

// Lieu de residence
$stmtLocations = $conn->query("SELECT name FROM locations");
$knownLocations = $stmtLocations->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($formData['lieu_residence'], $knownLocations)) {
    $formData['autre_lieu_residence'] = $formData['lieu_residence']; // Store the custom value
    $formData['lieu_residence'] = 'Autre'; // Set dropdown to 'Autre'
}

// Etablissement
$stmtSchools = $conn->query("SELECT nom FROM etablissements");
$knownSchools = $stmtSchools->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($formData['etablissement'], $knownSchools)) {
    $formData['autre_etablissement'] = $formData['etablissement'];
    $formData['etablissement'] = 'Autre';
}

// Domaine d'etudes
$stmtDomaines = $conn->query("SELECT nom FROM domaines_etudes");
$knownDomaines = $stmtDomaines->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($formData['domaine_etudes'], $knownDomaines)) {
    $formData['autre_domaine_etudes'] = $formData['domaine_etudes'];
    $formData['domaine_etudes'] = 'Autre';
}

// Niveau d'etudes
$stmtNiveaux = $conn->query("SELECT nom FROM niveaux_etudes");
$knownNiveaux = $stmtNiveaux->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($formData['niveau_etudes'], $knownNiveaux)) {
    $formData['autre_niveau_etudes'] = $formData['niveau_etudes'];
    $formData['niveau_etudes'] = 'Autre';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: edit-student.php?id=' . $student_id);
        exit();
    }

    // Sanitize and retrieve form data
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
        'projet_apres_formation' => trim($_POST['projet_apres_formation'] ?? ''),
        'autre_lieu_residence' => trim($_POST['autre_lieu_residence'] ?? ''),
        'nationalites' => $_POST['nationalites'] ?? '',
        'cv_path' => $student['cv_path'] ?? null // Keep existing CV path
    ];

    // Normalize nullable fields
    $formData['annee_arrivee'] = ($formData['annee_arrivee'] === null || $formData['annee_arrivee'] === '') ? null : (int)$formData['annee_arrivee'];
    $formData['precision_logement'] = $formData['precision_logement'] === '' ? null : $formData['precision_logement'];
    $formData['projet_apres_formation'] = $formData['projet_apres_formation'] === '' ? null : $formData['projet_apres_formation'];

    // Process Nationalities
    $validNats = [];
    $validIds = [];
    if (!empty($formData['nationalites'])) {
        $decoded = json_decode($formData['nationalites'], true);
        $names = [];

        // Handle Tagify formats
        if (is_array($decoded)) {
             if (isset($decoded[0]['value'])) {
                 $names = array_column($decoded, 'value');
             } else {
                 $names = $decoded;
             }
        }

        // Validate against DB
        if (!empty($names)) {
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $stmtVal = $conn->prepare("SELECT id_pays, nom_fr FROM pays WHERE nom_fr IN ($placeholders)");
            $stmtVal->execute($names);
            while ($row = $stmtVal->fetch(PDO::FETCH_ASSOC)) {
                $validNats[] = $row['nom_fr'];
                $validIds[] = $row['id_pays'];
            }
        }
    }
    $nationalites_json = !empty($validNats) ? json_encode($validNats, JSON_UNESCAPED_UNICODE) : null;
    
    // Note: 'Autre' options for lieu_residence, etablissement, domaine_etudes, niveau_etudes
    // are now handled by separate variables for database insertion,
    // ensuring the original selection remains in $formData for form re-rendering.
    $finalLieuResidenceForDb = $formData['lieu_residence'];
    if ($formData['lieu_residence'] === 'Autre') {
        $finalLieuResidenceForDb = $formData['autre_lieu_residence'];
    }

    $finalEtablissementForDb = $formData['etablissement'];
    if ($formData['etablissement'] === 'Autre') {
        $finalEtablissementForDb = $formData['autre_etablissement'];
    }

    $finalDomaineEtudesForDb = $formData['domaine_etudes'];
    if ($formData['domaine_etudes'] === 'Autre') {
        $finalDomaineEtudesForDb = $formData['autre_domaine_etudes'];
    }

    $finalNiveauEtudesForDb = $formData['niveau_etudes'];
    if ($formData['niveau_etudes'] === 'Autre') {
        $finalNiveauEtudesForDb = $formData['autre_niveau_etudes'];
    }

    // --- Validation ---
    $requiredFields = [
        'nom' => 'Le nom est requis.', 'prenom' => 'Le prénom est requis.', 'sexe' => 'Le sexe est requis.',
        'date_naissance' => 'La date de naissance est requise.', 'lieu_residence' => 'Le lieu de résidence est requis.',
        'etablissement' => 'L\'établissement est requis.', 'statut' => 'Le statut est requis.',
        'domaine_etudes' => 'Le domaine d\'études est requis.', 'niveau_etudes' => 'Le niveau d\'études est requis.',
        'telephone' => 'Le téléphone est requis.', 'email' => 'L\'email est requis.', 'type_logement' => 'Le type de logement est requis.'
    ];
    foreach ($requiredFields as $field => $message) {
        if (empty($formData[$field])) $errors[$field] = $message;
    }
    if (empty($formData['numero_identite'])) {
        $errors['numero_identite'] = 'Le numéro d\'identité est requis.';
    } else {
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE numero_identite = ? AND id_personne != ?");
        $stmt->execute([$formData['numero_identite'], $student_id]);
        if ($stmt->fetch()) $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';
    }

    // Email validation
    if (!isset($errors['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'adresse email n'est pas valide.";
    }
    // Check unique Email
    if (empty($errors['email'])) { // Only check uniqueness if format is valid
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE email = ? AND id_personne != ?");
        $stmt->execute([$formData['email'], $student_id]);
        if ($stmt->fetch()) $errors['email'] = 'Cette adresse email est déjà enregistrée.';
    }

    // Telephone validation
    if (!isset($errors['telephone']) && !isValidPhone($formData['telephone'])) {
        $errors['telephone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    }
    // Check unique Telephone
    if (empty($errors['telephone'])) { // Only check uniqueness if format is valid
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE telephone = ? AND id_personne != ?");
        $stmt->execute([$formData['telephone'], $student_id]);
        if ($stmt->fetch()) $errors['telephone'] = 'Ce numéro de téléphone est déjà enregistré.';
    }

    if (empty($errors['date_naissance'])) {
        $age = calculateAge($formData['date_naissance']);
        if ($age < 15) {
            $errors['date_naissance'] = "L'âge doit être d'au moins 15 ans.";
        }
    }

    // Handle file uploads (Identité and CV)
    $identitePath = $student['identite']; // Keep old path if no new file is uploaded
    $identiteUploadResult = handleFileUpload($_FILES['photo'] ?? [], ['jpg', 'jpeg', 'png', 'gif', 'pdf'], 2 * 1024 * 1024, 'uploads/students');
    if (!$identiteUploadResult['success']) {
        if ($identiteUploadResult['filepath'] !== null) { // Only set error if a file was actually attempted to be uploaded
             $errors['identite'] = $identiteUploadResult['message'];
        }
    } else {
        // If a new file was uploaded, delete the old one if it exists
        if ($identiteUploadResult['filepath'] !== null && $identitePath && file_exists($identitePath)) {
            unlink($identitePath);
        }
        $identitePath = $identiteUploadResult['filepath'];
    }
    $formData['identite'] = $identitePath;

    $cvPath = $student['cv_path']; // Keep old path if no new file is uploaded
    $cvUploadResult = handleFileUpload($_FILES['cv_file'] ?? [], ['pdf', 'png'], 5 * 1024 * 1024, 'uploads/students/cvs');
    if (!$cvUploadResult['success']) {
        if ($cvUploadResult['filepath'] !== null) { // Only set error if a file was actually attempted to be uploaded
             $errors['cv'] = $cvUploadResult['message'];
        }
    } else {
        // If a new file was uploaded, delete the old one if it exists
        if ($cvUploadResult['filepath'] !== null && $cvPath && file_exists($cvPath)) {
            unlink($cvPath);
        }
        $cvPath = $cvUploadResult['filepath'];
    }
    $formData['cv_path'] = $cvPath;

    // If no errors, update the database
    if (empty($errors)) {
        // Logic to add new school/domain/level if they don't exist
        if (!empty($finalEtablissementForDb)) {
            $stmt = $conn->prepare("SELECT id FROM etablissements WHERE nom = ?");
            $stmt->execute([$finalEtablissementForDb]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO etablissements (nom) VALUES (?)")->execute([$finalEtablissementForDb]);
            }
        }
        if (!empty($finalDomaineEtudesForDb)) {
            $stmt = $conn->prepare("SELECT id FROM domaines_etudes WHERE nom = ?");
            $stmt->execute([$finalDomaineEtudesForDb]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO domaines_etudes (nom) VALUES (?)")->execute([$finalDomaineEtudesForDb]);
            }
        }
        if (!empty($finalNiveauEtudesForDb)) {
            $stmt = $conn->prepare("SELECT id FROM niveaux_etudes WHERE nom = ?");
            $stmt->execute([$finalNiveauEtudesForDb]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO niveaux_etudes (nom) VALUES (?)")->execute([$finalNiveauEtudesForDb]);
            }
        }

        $sql = "UPDATE personnes SET 
            nom = :nom, prenom = :prenom, numero_identite = :numero_identite, sexe = :sexe, 
            date_naissance = :date_naissance, lieu_residence = :lieu_residence, etablissement = :etablissement, 
            statut = :statut, domaine_etudes = :domaine_etudes, niveau_etudes = :niveau_etudes, 
            telephone = :telephone, email = :email, annee_arrivee = :annee_arrivee, 
            type_logement = :type_logement, precision_logement = :precision_logement, 
            projet_apres_formation = :projet_apres_formation, identite = :identite,
            nationalites = :nationalites, cv_path = :cv_path
            WHERE id_personne = :id_personne";

        $params = [
            'nom' => $formData['nom'],
            'prenom' => $formData['prenom'],
            'numero_identite' => $formData['numero_identite'],
            'sexe' => $formData['sexe'],
            'date_naissance' => $formData['date_naissance'],
            'lieu_residence' => $finalLieuResidenceForDb,
            'etablissement' => $finalEtablissementForDb,
            'statut' => $formData['statut'],
            'domaine_etudes' => $finalDomaineEtudesForDb,
            'niveau_etudes' => $finalNiveauEtudesForDb,
            'telephone' => $formData['telephone'],
            'email' => $formData['email'],
            'annee_arrivee' => $formData['annee_arrivee'],
            'type_logement' => $formData['type_logement'],
            'precision_logement' => $formData['precision_logement'],
            'projet_apres_formation' => $formData['projet_apres_formation'],
            'identite' => $formData['identite'],
            'nationalites' => $nationalites_json,
            'cv_path' => $formData['cv_path'], // Added cv_path
            'id_personne' => $student_id
        ];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Update Nationalities Pivot
        // 1. Delete old
        $conn->prepare("DELETE FROM personne_pays WHERE id_personne = ?")->execute([$student_id]);
        
        // 2. Insert new
        if (!empty($validIds)) {
             $stmtPivot = $conn->prepare("INSERT IGNORE INTO personne_pays (id_personne, id_pays) VALUES (?, ?)");
             foreach ($validIds as $pid) {
                 $stmtPivot->execute([$student_id, $pid]);
             }
        }

        setFlashMessage('success', 'Informations modifiées avec succès');
        header('Location: students.php');
        exit();
    }
}

// --- Template Rendering ---
$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Define template paths
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/edit-student.html';

// Include and capture sidebar
ob_start();
require_once 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$validation_errors_json = '';
if (!empty($errors)) {
    $validation_errors_json = json_encode($errors);
}

// Read content template
$template = file_get_contents($templatePath);

// Prepare replacements for the content template
$sel = fn($value, $option) => $value === $option ? 'selected' : '';

$etablissementOptions = '';
foreach ($schools as $school) {
    $etablissementOptions .= "<option value=\"$school\" " . $sel($formData['etablissement'], $school) . ">$school</option>";
}
$domaineOptions = '';
foreach ($domaines as $domaine) {
    $domaineOptions .= "<option value=\"$domaine\" " . $sel($formData['domaine_etudes'], $domaine) . ">$domaine</option>";
}
$niveauOptions = '';
foreach ($niveaux as $niveau) {
    $niveauOptions .= "<option value=\"$niveau\" " . $sel($formData['niveau_etudes'], $niveau) . ">$niveau</option>";
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

$currentCvDisplay = '';
if (!empty($formData['cv_path'])) {
    $currentCvDisplay = '<div class="mt-2">CV actuel: <a href="' . htmlspecialchars($formData['cv_path']) . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i> Télécharger</a></div>';
}

$replacements = [
    '{{feedback_block}}' => !empty($errors) ? '<div class="alert alert-danger">Veuillez corriger les erreurs ci-dessous.</div>' : '',
    '{{form_action}}' => 'edit-student.php?id=' . $student_id,
    '{{csrf_token}}' => generateCsrfToken(),
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
    '{{projet_apres_formation}}' => htmlspecialchars($formData['projet_apres_formation'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{etablissement_options}}' => $etablissementOptions,
    '{{domaine_etudes_options}}' => $domaineOptions,
    '{{niveau_etudes_options}}' => $niveauOptions,
    '{{sexe_checked_Masculin}}' => $checked($formData['sexe'], 'Masculin'),
    '{{sexe_checked_Feminin}}' => $checked($formData['sexe'], 'Féminin'),
    '{{type_logement_sel_Colocation}}' => $sel($formData['type_logement'], 'Colocation'),
    '{{type_logement_sel_Famille}}' => $sel($formData['type_logement'], 'Famille'),
    '{{type_logement_sel_Hébergement temporaire}}' => $sel($formData['type_logement'], 'Hébergement temporaire'),
    '{{type_logement_sel_Location}}' => $sel($formData['type_logement'], 'Location'),
    '{{type_logement_sel_Résidence universitaire}}' => $sel($formData['type_logement'], 'Résidence universitaire'),
    '{{type_logement_sel_Autre}}' => $sel($formData['type_logement'], 'Autre'),
    '{{statut_sel_Élève}}' => $sel($formData['statut'], 'Élève'),
    '{{statut_sel_Étudiant}}' => $sel($formData['statut'], 'Étudiant'),
    '{{statut_sel_Stagiaire}}' => $sel($formData['statut'], 'Stagiaire'),
    '{{nationalites_value}}' => htmlspecialchars($nationalites_value, ENT_QUOTES, 'UTF-8'),
    '{{current_cv_display}}' => $currentCvDisplay,
    '{{autre_lieu_residence}}' => htmlspecialchars($formData['autre_lieu_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{autre_etablissement}}' => htmlspecialchars($formData['autre_etablissement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{autre_domaine_etudes}}' => htmlspecialchars($formData['autre_domaine_etudes'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{autre_niveau_etudes}}' => htmlspecialchars($formData['autre_niveau_etudes'] ?? '', ENT_QUOTES, 'UTF-8'),
];

$error_fields = ['nom', 'prenom', 'numero_identite', 'sexe', 'date_naissance', 'identite', 'telephone', 'email', 'lieu_residence', 'etablissement', 'statut', 'domaine_etudes', 'niveau_etudes', 'type_logement', 'cv']; // Added 'cv'
foreach ($error_fields as $field) {
    $replacements["{{error_$field}}"] = $errors[$field] ?? '';
    $replacements["{{is_invalid_$field}}"] = isset($errors[$field]) ? 'is-invalid' : '';
}

$contentHtml = strtr($template, $replacements);

// Read layout and perform final replacements
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Modifier l\'étudiant',
    '{{sidebar}}' => $sidebarHtml,
    '{{flash_json}}' => $flash_json,
    '{{validation_errors_json}}' => $validation_errors_json,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);