<?php

/**
 * Edit student page.
 * File: edit-student.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';
require_once 'functions/email-service.php';

// Authentication and Authorization — admin only
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    header('Location: dashboard.php');
    exit();
}

if (!hasPermission('students')) {
    setFlashMessage('error', 'Accès refusé : vous n\'avez pas la permission de modifier les membres.');
    header('Location: dashboard.php'); exit();
}

// Get student ID from URL
$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    header('Location: students.php');
    exit();
}

// Fetch student data from the database
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        setFlashMessage('error', 'Étudiant introuvable.');
        header('Location: students.php');
        exit();
    }

    // Fetch nationalities from pivot table
    $stmtNats = $conn->prepare("SELECT p.name_fr FROM countries p JOIN student_country pp ON p.id = pp.country_id WHERE pp.student_id = ?");
    $stmtNats->execute([$student_id]);
    $nationalitiesList = $stmtNats->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($nationalitiesList)) {
        $nationalities_value = json_encode($nationalitiesList, JSON_UNESCAPED_UNICODE);
    } else {
        // Fallback to legacy
        $nationalities_value = $student['nationalities'] ?? '';
        if (is_null($nationalities_value)) $nationalities_value = '';
    }

// Fetch data for dropdowns
$stmt = $conn->query("SELECT name FROM institutions ORDER BY name ASC");
$schools = $stmt->fetchAll(PDO::FETCH_COLUMN);
$schools[] = 'Autre';

$stmt = $conn->query("SELECT name FROM study_fields ORDER BY name ASC");
$studyFields = $stmt->fetchAll(PDO::FETCH_COLUMN);
$studyFields[] = 'Autre';

$stmt = $conn->query("SELECT name FROM study_levels ORDER BY name ASC");
$studyLevels = $stmt->fetchAll(PDO::FETCH_COLUMN);
$studyLevels[] = 'Autre';

// Initialize variables
$errors = [];
$formData = $student; // Pre-fill form with existing student data

// Special handling for 'Autre' fields to correctly display the form
// Check if current values are in the predefined lists for dropdowns.
// If not, it means it's a custom 'Autre' value.

// Lieu de residence
$stmtLocations = $conn->query("SELECT name FROM locations");
$knownLocations = $stmtLocations->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($formData['residence'], $knownLocations)) {
    $formData['other_residence'] = $formData['residence']; // Store the custom value
    $formData['residence'] = 'Autre'; // Set dropdown to 'Autre'
}

// Etablissement
if (!in_array($formData['institution'], $schools)) {
    $formData['other_institution'] = $formData['institution'];
    $formData['institution'] = 'Autre';
}

// Domaine d'etudes
if (!in_array($formData['study_field'], $studyFields)) {
    $formData['other_study_field'] = $formData['study_field'];
    $formData['study_field'] = 'Autre';
}

// Niveau d'etudes
if (!in_array($formData['study_level'], $studyLevels)) {
    $formData['other_study_level'] = $formData['study_level'];
    $formData['study_level'] = 'Autre';
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
        'last_name' => trim($_POST['last_name'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'gender' => $_POST['gender'] ?? '',
        'birth_date' => $_POST['birth_date'] ?? '',
        'residence' => trim($_POST['residence'] ?? ''),
        'institution' => trim($_POST['institution'] ?? ''),
        'other_institution' => trim($_POST['other_institution'] ?? ''),
        'status' => $_POST['status'] ?? '',
        'study_field' => trim($_POST['study_field'] ?? ''),
        'other_study_field' => trim($_POST['other_study_field'] ?? ''),
        'study_level' => trim($_POST['study_level'] ?? ''),
        'other_study_level' => trim($_POST['other_study_level'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'arrival_year' => $_POST['arrival_year'] ?? null,
        'housing_type' => $_POST['housing_type'] ?? '',
        'housing_details' => trim($_POST['housing_details'] ?? ''),
        'post_training_project' => trim($_POST['post_training_project'] ?? ''),
        'other_residence' => trim($_POST['other_residence'] ?? ''),
        'nationalities' => $_POST['nationalities'] ?? '',
        'graduation_date' => trim($_POST['graduation_date'] ?? ''),
        'cv_path' => $student['cv_path'] ?? null // Keep existing CV path
    ];

    // Normalize nullable fields
    $formData['arrival_year'] = ($formData['arrival_year'] === null || $formData['arrival_year'] === '') ? null : (int)$formData['arrival_year'];
    $formData['housing_details'] = $formData['housing_details'] === '' ? null : $formData['housing_details'];
    $formData['post_training_project'] = $formData['post_training_project'] === '' ? null : $formData['post_training_project'];

    // Process Nationalities
    $validNats = [];
    $validIds = [];
    if (!empty($formData['nationalities'])) {
        $decoded = json_decode($formData['nationalities'], true);
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
            $stmtVal = $conn->prepare("SELECT id, name_fr FROM countries WHERE name_fr IN ($placeholders)");
            $stmtVal->execute($names);
            while ($row = $stmtVal->fetch(PDO::FETCH_ASSOC)) {
                $validNats[] = $row['name_fr'];
                $validIds[] = $row['id'];
            }
        }
    }
    $nationalities_json = !empty($validNats) ? json_encode($validNats, JSON_UNESCAPED_UNICODE) : null;
    
    $finalLieuResidenceForDb = $formData['residence'];
    if ($formData['residence'] === 'Autre') {
        $finalLieuResidenceForDb = $formData['other_residence'];
    }

    $finalEtablissementForDb = $formData['institution'];
    if ($formData['institution'] === 'Autre') {
        $finalEtablissementForDb = $formData['other_institution'];
    }

    $finalDomaineEtudesForDb = $formData['study_field'];
    if ($formData['study_field'] === 'Autre') {
        $finalDomaineEtudesForDb = $formData['other_study_field'];
    }

    $finalNiveauEtudesForDb = $formData['study_level'];
    if ($formData['study_level'] === 'Autre') {
        $finalNiveauEtudesForDb = $formData['other_study_level'];
    }

    // --- Validation ---
    $requiredFields = [
        'last_name' => 'Le nom est requis.', 'first_name' => 'Le prénom est requis.', 'gender' => 'Le sexe est requis.',
        'residence' => 'Le lieu de résidence est requis.',
        'institution' => 'L\'établissement est requis.', 'status' => 'Le statut est requis.',
        'study_field' => 'Le domaine d\'études est requis.', 'study_level' => 'Le niveau d\'études est requis.',
        'phone' => 'Le téléphone est requis.', 'email' => 'L\'email est requis.', 'housing_type' => 'Le type de logement est requis.'
    ];
    foreach ($requiredFields as $field => $message) {
        if (empty($formData[$field])) $errors[$field] = $message;
    }

    // Email validation
    if (!isset($errors['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'adresse email n'est pas valide.";
    }
    // Check unique Email
    if (empty($errors['email'])) { // Only check uniqueness if format is valid
        $stmt = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $stmt->execute([$formData['email'], $student_id]);
        if ($stmt->fetch()) $errors['email'] = 'Cette adresse email est déjà enregistrée.';
    }

    // Telephone validation
    if (!isset($errors['phone']) && !isValidPhone($formData['phone'])) {
        $errors['phone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    }
    // Check unique Telephone
    if (empty($errors['phone'])) { // Only check uniqueness if format is valid
        $stmt = $conn->prepare("SELECT id FROM students WHERE phone = ? AND id != ?");
        $stmt->execute([$formData['phone'], $student_id]);
        if ($stmt->fetch()) $errors['phone'] = 'Ce numéro de téléphone est déjà enregistré.';
    }

    if (!empty($formData['birth_date']) && empty($errors['birth_date'])) {
        $age = calculateAge($formData['birth_date']);
        if ($age < 15) {
            $errors['birth_date'] = "L'âge doit être d'au moins 15 ans.";
        }
    }

    // Handle file uploads (Identity document and CV)
    $identityDocumentPath = $student['identity_document']; // Keep old path if no new file is uploaded
    $identityUploadResult = handleFileUpload($_FILES['photo'] ?? [], ['jpg', 'jpeg', 'png', 'gif', 'pdf'], 2 * 1024 * 1024, 'uploads/students');
    if (!$identityUploadResult['success']) {
        if ($identityUploadResult['filepath'] !== null) { // Only set error if a file was actually attempted to be uploaded
             $errors['identity_document'] = $identityUploadResult['message'];
        }
    } else {
        // Only replace path when a new file was actually uploaded
        if ($identityUploadResult['filepath'] !== null) {
            safeUnlink($identityDocumentPath);
            $identityDocumentPath = $identityUploadResult['filepath'];
        }
    }
    $formData['identity_document'] = $identityDocumentPath;

    $cvPath = $student['cv_path']; // Keep old path if no new file is uploaded
    $cvUploadResult = handleFileUpload($_FILES['cv_file'] ?? [], ['pdf', 'png'], 5 * 1024 * 1024, 'uploads/students/cvs');
    if (!$cvUploadResult['success']) {
        if ($cvUploadResult['filepath'] !== null) { // Only set error if a file was actually attempted to be uploaded
             $errors['cv'] = $cvUploadResult['message'];
        }
    } else {
        // Only replace path when a new file was actually uploaded
        if ($cvUploadResult['filepath'] !== null) {
            safeUnlink($cvPath);
            $cvPath = $cvUploadResult['filepath'];
        }
    }
    $formData['cv_path'] = $cvPath;

    // If no errors, update the database
    if (empty($errors)) {
        // Logic to add new school/domain/level if they don't exist
        if (!empty($finalEtablissementForDb)) {
            $stmt = $conn->prepare("SELECT id FROM institutions WHERE name = ?");
            $stmt->execute([$finalEtablissementForDb]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO institutions (name) VALUES (?)")->execute([$finalEtablissementForDb]);
            }
        }
        if (!empty($finalDomaineEtudesForDb)) {
            $stmt = $conn->prepare("SELECT id FROM study_fields WHERE name = ?");
            $stmt->execute([$finalDomaineEtudesForDb]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO study_fields (name) VALUES (?)")->execute([$finalDomaineEtudesForDb]);
            }
        }
        if (!empty($finalNiveauEtudesForDb)) {
            $stmt = $conn->prepare("SELECT id FROM study_levels WHERE name = ?");
            $stmt->execute([$finalNiveauEtudesForDb]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO study_levels (name) VALUES (?)")->execute([$finalNiveauEtudesForDb]);
            }
        }

        // Handle graduation_date: set to today if Diplômé and no date given; null if not Diplômé
        $finalGraduationDate = null;
        if ($formData['status'] === 'GRADUATE' || $formData['status'] === 'Diplômé') {
            $finalGraduationDate = !empty($formData['graduation_date'])
                ? $formData['graduation_date']
                : date('Y-m-d');
        }

        $sql = "UPDATE students SET
            last_name = :last_name, first_name = :first_name, gender = :gender,
            birth_date = :birth_date, residence = :residence, institution = :institution,
            status = :status, study_field = :study_field, study_level = :study_level,
            phone = :phone, email = :email, arrival_year = :arrival_year,
            housing_type = :housing_type, housing_details = :housing_details,
            post_training_project = :post_training_project, identity_document = :identity_document,
            nationalities = :nationalities, cv_path = :cv_path,
            graduation_date = :graduation_date
            WHERE id = :id";

        $params = [
            'last_name' => $formData['last_name'],
            'first_name' => $formData['first_name'],
            'gender' => $formData['gender'],
            'birth_date' => $formData['birth_date'] ?: null,
            'residence' => $finalLieuResidenceForDb,
            'institution' => $finalEtablissementForDb,
            'status' => $formData['status'],
            'study_field' => $finalDomaineEtudesForDb,
            'study_level' => $finalNiveauEtudesForDb,
            'phone' => $formData['phone'],
            'email' => $formData['email'],
            'arrival_year' => $formData['arrival_year'],
            'housing_type' => $formData['housing_type'],
            'housing_details' => $formData['housing_details'],
            'post_training_project' => $formData['post_training_project'],
            'identity_document' => $formData['identity_document'],
            'nationalities' => $nationalities_json,
            'cv_path' => $formData['cv_path'],
            'graduation_date' => $finalGraduationDate,
            'id' => $student_id
        ];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Update Nationalities Pivot
        $conn->prepare("DELETE FROM student_country WHERE student_id = ?")->execute([$student_id]);
        if (!empty($validIds)) {
             $stmtPivot = $conn->prepare("INSERT IGNORE INTO student_country (student_id, country_id) VALUES (?, ?)");
             foreach ($validIds as $pid) {
                 $stmtPivot->execute([$student_id, $pid]);
             }
        }

        // Notify student
        $notifBody = renderEmailTemplate(__DIR__ . '/templates/emails/admin-update-notification.html', [
            'id'             => $student_id,
            'first_name'     => $formData['first_name'],
            'last_name'      => $formData['last_name'],
            'email'          => $formData['email'],
            'phone'          => $formData['phone'],
            'status'         => $formData['status'],
            'institution'    => $finalEtablissementForDb,
            'study_field'    => $finalDomaineEtudesForDb,
            'study_level'    => $finalNiveauEtudesForDb,
            'residence'      => $finalLieuResidenceForDb,
            'housing_type'   => $formData['housing_type'],
            'update_date'    => date('d/m/Y à H:i'),
        ]);
        sendMail($formData['email'], 'Votre dossier a été mis à jour – AEESGS', $notifBody);

        setFlashMessage('success', 'Informations modifiées avec succès');
        header('Location: students.php');
        exit();
    }
}

// --- Template Rendering ---
$role = $_SESSION['role'];
$nom = $_SESSION['last_name'] ?? '';
$prenom = $_SESSION['first_name'] ?? '';

// Define template paths
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/edit-student.html';

// Include and capture sidebar
ob_start();
require_once 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$flash = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

$validation_errors_json = !empty($errors) ? json_encode($errors) : '';

// Read content template
$template = file_get_contents($templatePath);

// Prepare replacements for the content template
$sel = fn($value, $option) => $value === $option ? 'selected' : '';

$institutionOptions = '';
foreach ($schools as $school) {
    $institutionOptions .= "<option value=\"$school\" " . $sel($formData['institution'], $school) . ">$school</option>";
}
$studyFieldOptions = '';
foreach ($studyFields as $domaine) {
    $studyFieldOptions .= "<option value=\"$domaine\" " . $sel($formData['study_field'], $domaine) . ">$domaine</option>";
}
$studyLevelOptions = '';
foreach ($studyLevels as $niveau) {
    $studyLevelOptions .= "<option value=\"$niveau\" " . $sel($formData['study_level'], $niveau) . ">$niveau</option>";
}

// Fetch locations
$stmt = $conn->query("SELECT region, name FROM locations ORDER BY CASE WHEN region LIKE 'Dakar%' THEN 0 ELSE 1 END, region ASC, name ASC");
$locations = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

$residenceOptions = '<option value="" selected>Sélectionnez un lieu</option>';
foreach ($locations as $region => $cities) {
    $residenceOptions .= "<optgroup label=\"$region\">";
    foreach ($cities as $city) {
        $selected = ($formData['residence'] ?? '') === $city ? 'selected' : '';
        $residenceOptions .= "<option value=\"$city\" $selected>$city</option>";
    }
    $residenceOptions .= "</optgroup>";
}
$residenceOptions .= '<option value="Autre">Autre</option>';

$arrivalYearOptions = '<option value="" selected>Sélectionnez</option>';
$currentYear = date('Y');
for ($year = $currentYear; $year >= 1990; $year--) {
    $selected = ($formData['arrival_year'] ?? '') == $year ? 'selected' : '';
    $arrivalYearOptions .= "<option value=\"$year\" $selected>$year</option>";
}

$maxBirthDate = ($currentYear - 15) . '-12-31';

$sel = fn($value, $option) => $value === $option ? 'selected' : '';
$checked = fn($value, $option) => $value === $option ? 'checked' : '';

$currentCvDisplay = '';
if (!empty($formData['cv_path'])) {
    $currentCvDisplay = '<div class="mt-2">CV actuel: <a href="' . htmlspecialchars($formData['cv_path'] ?? '') . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-download"></i> Télécharger</a></div>';
}

$replacements = [
    '{{feedback_block}}' => !empty($errors) ? '<div class="alert alert-danger">Veuillez corriger les erreurs ci-dessous.</div>' : '',
    '{{form_action}}' => 'edit-student.php?id=' . $student_id,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{last_name}}' => htmlspecialchars($formData['last_name'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{first_name}}' => htmlspecialchars($formData['first_name'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{birth_date}}' => htmlspecialchars($formData['birth_date'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{max_birth_date}}' => $maxBirthDate,
    '{{phone}}' => htmlspecialchars($formData['phone'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{residence}}' => htmlspecialchars($formData['residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{residence_options}}' => $residenceOptions,
    '{{arrival_year_options}}' => $arrivalYearOptions,
    '{{housing_details}}' => htmlspecialchars($formData['housing_details'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{post_training_project}}' => htmlspecialchars($formData['post_training_project'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{institution_options}}' => $institutionOptions,
    '{{study_field_options}}' => $studyFieldOptions,
    '{{study_level_options}}' => $studyLevelOptions,
    '{{gender_checked_Male}}' => $checked($formData['gender'], 'Masculin') || $checked($formData['gender'], 'Male'),
    '{{gender_checked_Female}}' => $checked($formData['gender'], 'Féminin') || $checked($formData['gender'], 'Female'),
    '{{housing_type_sel_Colocation}}' => $sel($formData['housing_type'], 'Colocation'),
    '{{housing_type_sel_Famille}}' => $sel($formData['housing_type'], 'Famille'),
    '{{housing_type_sel_Hébergement temporaire}}' => $sel($formData['housing_type'], 'Hébergement temporaire'),
    '{{housing_type_sel_Location}}' => $sel($formData['housing_type'], 'Location'),
    '{{housing_type_sel_Résidence universitaire}}' => $sel($formData['housing_type'], 'Résidence universitaire'),
    '{{housing_type_sel_Autre}}' => $sel($formData['housing_type'], 'Autre'),
    '{{status_sel_PUPIL}}' => $sel($formData['status'], 'ELEVE') || $sel($formData['status'], 'PUPIL') || $sel($formData['status'], 'Élève'),
    '{{status_sel_STUDENT}}' => $sel($formData['status'], 'ETUDIANT') || $sel($formData['status'], 'STUDENT') || $sel($formData['status'], 'Étudiant'),
    '{{status_sel_TRAINEE}}' => $sel($formData['status'], 'STAGIAIRE') || $sel($formData['status'], 'TRAINEE') || $sel($formData['status'], 'Stagiaire'),
    '{{status_sel_GRADUATE}}' => $sel($formData['status'], 'GRADUATE') || $sel($formData['status'], 'Diplômé'),
    '{{graduation_date}}' => htmlspecialchars($formData['graduation_date'] ?? ($student['graduation_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
    '{{nationalities_value}}' => htmlspecialchars($nationalities_value, ENT_QUOTES, 'UTF-8'),
    '{{current_cv_display}}' => $currentCvDisplay,
    '{{other_residence}}' => htmlspecialchars($formData['other_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{other_institution}}' => htmlspecialchars($formData['other_institution'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{other_study_field}}' => htmlspecialchars($formData['other_study_field'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{other_study_level}}' => htmlspecialchars($formData['other_study_level'] ?? '', ENT_QUOTES, 'UTF-8'),
];

$error_fields = ['last_name', 'first_name', 'gender', 'birth_date', 'identity_document', 'phone', 'email', 'residence', 'institution', 'status', 'study_field', 'study_level', 'housing_type', 'cv'];
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
