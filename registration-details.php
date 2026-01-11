<?php
/**
 * Page de détails post-inscription
 * Fichier: registration-details.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Check if we have a valid registration session
if (!isset($_SESSION['registration_student_id'])) {
    header('Location: index.php');
    exit();
}

$student_id = $_SESSION['registration_student_id'];
$action = $_GET['action'] ?? 'view';

// Handle "Finish" action
if ($action === 'finish') {
    unset($_SESSION['registration_student_id']);
    header('Location: index.php');
    exit();
}

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM personnes WHERE id_personne = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    // Should not happen, but safe fallback
    unset($_SESSION['registration_student_id']);
    header('Location: index.php');
    exit();
}

// Handle Update (POST)
$errors = [];
$formData = $student; // Pre-fill with existing data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header("Location: registration-details.php?action=edit");
        exit();
    }

    // Reuse validation and update logic similar to register.php/edit-student.php
    // ... (This logic is duplicated from edit-student.php but simplified for self-update)
    
    // 1. Sanitize
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
        'projet_apres_formation' => trim($_POST['projet_apres_formation'] ?? '')
    ];

    // Handle 'Other' options
    if ($formData['lieu_residence'] === 'Autre') $formData['lieu_residence'] = $formData['autre_lieu_residence'];
    if ($formData['etablissement'] === 'Autre') $formData['etablissement'] = $formData['autre_etablissement'];
    if ($formData['domaine_etudes'] === 'Autre') $formData['domaine_etudes'] = $formData['autre_domaine_etudes'];
    if ($formData['niveau_etudes'] === 'Autre') $formData['niveau_etudes'] = $formData['autre_niveau_etudes'];

    // Normalize
    $formData['annee_arrivee'] = ($formData['annee_arrivee'] === null || $formData['annee_arrivee'] === '') ? null : (int)$formData['annee_arrivee'];
    $formData['precision_logement'] = $formData['precision_logement'] === '' ? null : $formData['precision_logement'];
    $formData['projet_apres_formation'] = $formData['projet_apres_formation'] === '' ? null : $formData['projet_apres_formation'];
    $formData['age'] = null; // Will be recalculated

    // Process Nationalities
    $nationalites_json = null;
    if (!empty($formData['nationalites'])) {
        $decoded = json_decode($formData['nationalites'], true);
        if (is_array($decoded)) {
             // Handle Tagify format [{"value":"X"}] or simple array
             if (isset($decoded[0]['value'])) {
                 $list = array_column($decoded, 'value');
                 $nationalites_json = json_encode($list, JSON_UNESCAPED_UNICODE);
             } else {
                 $nationalites_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
             }
        }
    }
    
    // Validation
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
        // Check duplicate excluding self
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE numero_identite = ? AND id_personne != ?");
        $stmt->execute([$formData['numero_identite'], $student_id]);
        if ($stmt->fetch()) $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';
    }

    if (!isset($errors['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "L'adresse email n'est pas valide.";
    } else {
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE email = ? AND id_personne != ?");
        $stmt->execute([$formData['email'], $student_id]);
        if ($stmt->fetch()) $errors['email'] = 'Cet email est déjà utilisé.';
    }

    if (!isset($errors['telephone']) && !isValidPhone($formData['telephone'])) {
        $errors['telephone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    } else {
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE telephone = ? AND id_personne != ?");
        $stmt->execute([$formData['telephone'], $student_id]);
        if ($stmt->fetch()) $errors['telephone'] = 'Ce numéro est déjà utilisé.';
    }

    if (empty($errors['date_naissance'])) {
        $formData['age'] = calculateAge($formData['date_naissance']);
        if ($formData['age'] < 15) {
            $errors['date_naissance'] = "L'âge doit être d'au moins 15 ans.";
        }
    }

    // Handle File Upload
    $identitePath = $student['identite'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // ... (Upload logic reused)
        $photoTmpPath = $_FILES['photo']['tmp_name'];
        $photoName = $_FILES['photo']['name'];
        $photoSize = $_FILES['photo']['size'];
        $photoExtension = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        
        if (in_array($photoExtension, $allowedExtensions)) {
            if ($photoSize < 2000000) {
                 $newFileName = uniqid('', true) . '.' . $photoExtension;
                 $uploadPath = 'uploads/students/' . $newFileName;
                 if (move_uploaded_file($photoTmpPath, $uploadPath)) {
                     // Delete old file if exists
                     if ($identitePath && file_exists($identitePath)) {
                         unlink($identitePath);
                     }
                     $identitePath = $uploadPath;
                 }
            } else {
                $errors['identite'] = "Le fichier est trop volumineux (max 2MB).";
            }
        }
    }
    $formData['identite'] = $identitePath;

    if (empty($errors)) {
        // Update DB
        $sql = "UPDATE personnes SET 
            nom = :nom, prenom = :prenom, numero_identite = :numero_identite, sexe = :sexe, age = :age,
            date_naissance = :date_naissance, lieu_residence = :lieu_residence, etablissement = :etablissement, 
            statut = :statut, domaine_etudes = :domaine_etudes, niveau_etudes = :niveau_etudes, 
            telephone = :telephone, email = :email, annee_arrivee = :annee_arrivee, 
            type_logement = :type_logement, precision_logement = :precision_logement, 
            projet_apres_formation = :projet_apres_formation, identite = :identite,
            nationalites = :nationalites
            WHERE id_personne = :id_personne";
        
        $params = [
            ':nom' => $formData['nom'], ':prenom' => $formData['prenom'], ':numero_identite' => $formData['numero_identite'],
            ':sexe' => $formData['sexe'], ':age' => $formData['age'], ':date_naissance' => $formData['date_naissance'],
            ':lieu_residence' => $formData['lieu_residence'], ':etablissement' => $formData['etablissement'],
            ':statut' => $formData['statut'], ':domaine_etudes' => $formData['domaine_etudes'],
            ':niveau_etudes' => $formData['niveau_etudes'], ':telephone' => $formData['telephone'],
            ':email' => $formData['email'], ':annee_arrivee' => $formData['annee_arrivee'],
            ':type_logement' => $formData['type_logement'], ':precision_logement' => $formData['precision_logement'],
            ':projet_apres_formation' => $formData['projet_apres_formation'], ':identite' => $formData['identite'],
            ':nationalites' => $nationalites_json,
            ':id_personne' => $student_id
        ];
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        setFlashMessage('success', 'Vos informations ont été mises à jour.');
        header('Location: registration-details.php'); // Go back to view
        exit();
    } else {
        // Validation errors - stay on edit mode
        $action = 'edit';
    }
}

// Prepare View
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => 'active',
    '{{login_active}}' => '',
]);

$flash = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

// -------------------------------------------------------------------------
// RENDER: EDIT MODE
// -------------------------------------------------------------------------
if ($action === 'edit') {
    // Reuse most logic from register.php to populate dropdowns
    $stmt = $conn->query("SELECT nom FROM etablissements ORDER BY nom ASC");
    $schools = $stmt->fetchAll(PDO::FETCH_COLUMN); $schools[] = 'Autre';
    
    $stmt = $conn->query("SELECT nom FROM domaines_etudes ORDER BY nom ASC");
    $domaines = $stmt->fetchAll(PDO::FETCH_COLUMN); $domaines[] = 'Autre';
    
    $stmt = $conn->query("SELECT nom FROM niveaux_etudes ORDER BY nom ASC");
    $niveaux = $stmt->fetchAll(PDO::FETCH_COLUMN); $niveaux[] = 'Autre';
    
    $stmt = $conn->query("SELECT region, name FROM locations ORDER BY CASE WHEN region LIKE 'Dakar%' THEN 0 ELSE 1 END, region ASC, name ASC");
    $locations = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
    
    // Build Options HTML (reusing loops from register.php logic essentially)
    $sel = fn($v, $o) => $v === $o ? 'selected' : '';
    $chk = fn($v, $o) => $v === $o ? 'checked' : '';
    
    $etablissementOptions = ''; foreach ($schools as $s) $etablissementOptions .= "<option value=\"$s\" " . $sel($formData['etablissement'], $s) . ">$s</option>";
    $domaineOptions = ''; foreach ($domaines as $d) $domaineOptions .= "<option value=\"$d\" " . $sel($formData['domaine_etudes'], $d) . ">$d</option>";
    $niveauOptions = ''; foreach ($niveaux as $n) $niveauOptions .= "<option value=\"$n\" " . $sel($formData['niveau_etudes'], $n) . ">$n</option>";
    
    $lieuResidenceOptions = '<option value="">Sélectionnez</option>';
    foreach ($locations as $reg => $cities) {
        $lieuResidenceOptions .= "<optgroup label=\"$reg\">";
        foreach ($cities as $c) $lieuResidenceOptions .= "<option value=\"$c\" " . $sel($formData['lieu_residence'], $c) . ">$c</option>";
        $lieuResidenceOptions .= "</optgroup>";
    }
    $lieuResidenceOptions .= '<option value="Autre" '.$sel($formData['lieu_residence'], 'Autre').'>Autre</option>';
    
    $anneeArriveeOptions = '<option value="">Sélectionnez</option>';
    for ($y = date('Y'); $y >= 1990; $y--) $anneeArriveeOptions .= "<option value=\"$y\" " . $sel($formData['annee_arrivee'], $y) . ">$y</option>";

    $template = file_get_contents(__DIR__ . '/templates/registration-edit.html');
    
    // Prepare Nationalities Value
    $nat_val = $formData['nationalites'] ?? '';
    // If it's the raw JSON from DB update, we might need to be careful, but the template expects value=""
    // If it is an array (from POST error), we need to encode it back or handle it.
    // The Tagify input expects a CSV string or JSON string. 
    // In edit-student.php we used $nationalites_value
    if (is_array($formData['nationalites'] ?? null)) {
         // It came from POST array, tagify needs a string
         // Actually Tagify sends JSON string in POST usually? 
         // Let's ensure it's a string for the value attribute
    }
    
    $replacements = [
        '{{header}}' => $headerHtml, '{{footer}}' => $footerTpl, '{{flash_json}}' => $flash_json,
        '{{validation_errors_json}}' => json_encode($errors),
        '{{csrf_token}}' => generateCsrfToken(),
        '{{form_action}}' => 'registration-details.php', // POST back to self
        // Field Values
        '{{nom}}' => htmlspecialchars($formData['nom']), '{{prenom}}' => htmlspecialchars($formData['prenom']),
        '{{numero_identite}}' => htmlspecialchars($formData['numero_identite']), 
        '{{date_naissance}}' => htmlspecialchars($formData['date_naissance']), '{{max_birth_date}}' => (date('Y')-15).'-12-31',
        '{{telephone}}' => htmlspecialchars($formData['telephone']), '{{email}}' => htmlspecialchars($formData['email']),
        '{{precision_logement}}' => htmlspecialchars($formData['precision_logement']), 
        '{{projet_apres_formation}}' => htmlspecialchars($formData['projet_apres_formation']),
        // Options
        '{{lieu_residence_options}}' => $lieuResidenceOptions,
        '{{etablissement_options}}' => $etablissementOptions,
        '{{domaine_etudes_options}}' => $domaineOptions,
        '{{niveau_etudes_options}}' => $niveauOptions,
        '{{annee_arrivee_options}}' => $anneeArriveeOptions,
        // Checks/Selects
        '{{sexe_checked_Masculin}}' => $chk($formData['sexe'], 'Masculin'),
        '{{sexe_checked_Feminin}}' => $chk($formData['sexe'], 'Féminin'),
        '{{type_logement_sel_Colocation}}' => $sel($formData['type_logement'], 'Colocation'),
        '{{type_logement_sel_Famille}}' => $sel($formData['type_logement'], 'Famille'),
        '{{type_logement_sel_Hébergement temporaire}}' => $sel($formData['type_logement'], 'Hébergement temporaire'),
        '{{type_logement_sel_Location}}' => $sel($formData['type_logement'], 'Location'),
        '{{type_logement_sel_Résidence universitaire}}' => $sel($formData['type_logement'], 'Résidence universitaire'),
        '{{type_logement_sel_Autre}}' => $sel($formData['type_logement'], 'Autre'),
        '{{type_logement_sel_none}}' => empty($formData['type_logement']) ? 'selected' : '',
        '{{statut_sel_Élève}}' => $sel($formData['statut'], 'Élève'),
        '{{statut_sel_Étudiant}}' => $sel($formData['statut'], 'Étudiant'),
        '{{statut_sel_Stagiaire}}' => $sel($formData['statut'], 'Stagiaire'),
        '{{statut_sel_none}}' => empty($formData['statut']) ? 'selected' : '',
        // Nationalities - If stored as JSON in DB, just pass it.
        // Tagify should handle the JSON value string `[{"value":"Mali"}]`
        '{{nationalites_value}}' => htmlspecialchars($student['nationalites'] ?? ''), 
    ];
    
    // Errors
    $fields = ['nom', 'prenom', 'numero_identite', 'sexe', 'date_naissance', 'identite', 'telephone', 'email', 'lieu_residence', 'etablissement', 'statut', 'domaine_etudes', 'niveau_etudes', 'type_logement'];
    foreach ($fields as $f) {
        $replacements["{{error_$f}}"] = $errors[$f] ?? '';
        $replacements["{{is_invalid_$f}}"] = isset($errors[$f]) ? 'is-invalid' : '';
    }

    echo strtr($template, $replacements);
    exit();
}

// -------------------------------------------------------------------------
// RENDER: VIEW MODE (Default)
// -------------------------------------------------------------------------
$template = file_get_contents(__DIR__ . '/templates/registration-details.html');

// Prepare display data
$student['nationalites_display'] = 'N/A';
if (!empty($student['nationalites'])) {
    $nat_arr = json_decode($student['nationalites'], true);
    if (is_array($nat_arr)) {
        $student['nationalites_display'] = implode(', ', $nat_arr);
    }
}

$replacements = [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{flash_json}}' => $flash_json,
    // Data
    '{{nom}}' => htmlspecialchars($student['nom']),
    '{{prenom}}' => htmlspecialchars($student['prenom']),
    '{{email}}' => htmlspecialchars($student['email']),
    '{{telephone}}' => htmlspecialchars($student['telephone']),
    '{{sexe}}' => htmlspecialchars($student['sexe']),
    '{{date_naissance}}' => htmlspecialchars($student['date_naissance']),
    '{{numero_identite}}' => htmlspecialchars($student['numero_identite']),
    '{{lieu_residence}}' => htmlspecialchars($student['lieu_residence']),
    '{{statut}}' => htmlspecialchars($student['statut']),
    '{{etablissement}}' => htmlspecialchars($student['etablissement']),
    '{{niveau_etudes}}' => htmlspecialchars($student['niveau_etudes']),
    '{{domaine_etudes}}' => htmlspecialchars($student['domaine_etudes']),
    '{{annee_arrivee}}' => htmlspecialchars($student['annee_arrivee'] ?? 'N/A'),
    '{{type_logement}}' => htmlspecialchars($student['type_logement']),
    '{{nationalites}}' => htmlspecialchars($student['nationalites_display']),
    '{{projet_apres_formation}}' => !empty($student['projet_apres_formation']) ? nl2br(htmlspecialchars($student['projet_apres_formation'])) : 'Aucun projet spécifié',
    // Logic for Image
    '{{identite_url}}' => !empty($student['identite']) ? $student['identite'] : 'assets/img/placeholder.png',
];

echo strtr($template, $replacements);
?>
