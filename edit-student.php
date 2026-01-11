<?php

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

    // Decode nationalities for the form
    // If it's stored as JSON ["Mali", "Senegal"], we can pass it directly to the value attribute
    // Tagify will parse it.
    $nationalites_value = $student['nationalites'] ?? ''; 
    // If null, make it empty string
    if (is_null($nationalites_value)) $nationalites_value = '';

    // Préparer les données pour le formulaire
    $formData = [
        'id' => $student['id_personne'],


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
        'nationalites' => $_POST['nationalites'] ?? ''
    ];

    // Normalize nullable fields
    $formData['annee_arrivee'] = ($formData['annee_arrivee'] === null || $formData['annee_arrivee'] === '') ? null : (int)$formData['annee_arrivee'];
    $formData['precision_logement'] = $formData['precision_logement'] === '' ? null : $formData['precision_logement'];
    $formData['projet_apres_formation'] = $formData['projet_apres_formation'] === '' ? null : $formData['projet_apres_formation'];

    // Process Nationalities
    $nationalites_json = null;
    if (!empty($formData['nationalites'])) {
        $decoded = json_decode($formData['nationalites'], true);
        // Tagify sends [{"value":"Mali"}, ...] OR "Mali, Senegal" depending on mode.
        // If we get "value" objects:
        if (is_array($decoded) && isset($decoded[0]['value'])) {
             $list = array_column($decoded, 'value');
             $nationalites_json = json_encode($list, JSON_UNESCAPED_UNICODE);
        } elseif (is_array($decoded)) {
             // Already a simple array?
             $nationalites_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }
    }
    
    // Handle 'Other' options
    if ($formData['lieu_residence'] === 'Autre') $formData['lieu_residence'] = $formData['autre_lieu_residence'];
    if ($formData['etablissement'] === 'Autre') $formData['etablissement'] = $formData['autre_etablissement'];
    if ($formData['domaine_etudes'] === 'Autre') $formData['domaine_etudes'] = $formData['autre_domaine_etudes'];
    if ($formData['niveau_etudes'] === 'Autre') $formData['niveau_etudes'] = $formData['autre_niveau_etudes'];

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
    if (!isset($errors['telephone']) && !isValidPhone($formData['telephone'])) {
        $errors['telephone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    }

    if (empty($errors['date_naissance'])) {
        $age = calculateAge($formData['date_naissance']);
        if ($age < 15) {
            $errors['date_naissance'] = "L'âge doit être d'au moins 15 ans.";
        }
    }

    // Handle file upload
    $identitePath = $student['identite']; // Keep old path if no new file is uploaded
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoTmpPath = $_FILES['photo']['tmp_name'];
        $photoName = $_FILES['photo']['name'];
        $photoSize = $_FILES['photo']['size'];
        $photoExtension = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

        if (in_array($photoExtension, $allowedExtensions)) {
            if ($photoSize < 2000000) { // 2MB
                $newFileName = uniqid('', true) . '.' . $photoExtension;
                $uploadPath = 'uploads/students/' . $newFileName;
                if (move_uploaded_file($photoTmpPath, $uploadPath)) {
                    if ($identitePath && file_exists($identitePath)) {
                        unlink($identitePath);
                    }
                    $identitePath = $uploadPath;
                } else {
                    $errors['identite'] = "Erreur lors de l\'upload de l\'image.";
                }
            } else {
                $errors['identite'] = "L\'image est trop volumineuse (max 2MB).";
            }
        } else {
            $errors['identite'] = "Le format du fichier n\'est pas supporté.";
        }
    }
    $formData['identite'] = $identitePath;

    // If no errors, update the database
    if (empty($errors)) {
        // Logic to add new school/domain/level if they don't exist
        if (!empty($formData['etablissement'])) {
            $stmt = $conn->prepare("SELECT id FROM etablissements WHERE nom = ?");
            $stmt->execute([$formData['etablissement']]);
            if ($stmt->fetchColumn() === false) {
                $conn->prepare("INSERT INTO etablissements (nom) VALUES (?)")->execute([$formData['etablissement']]);
            }
        }
        // Similar logic for domaine_etudes and niveaux_etudes...

        $sql = "UPDATE personnes SET 
            nom = :nom, prenom = :prenom, numero_identite = :numero_identite, sexe = :sexe, 
            date_naissance = :date_naissance, lieu_residence = :lieu_residence, etablissement = :etablissement, 
            statut = :statut, domaine_etudes = :domaine_etudes, niveau_etudes = :niveau_etudes, 
            telephone = :telephone, email = :email, annee_arrivee = :annee_arrivee, 
            type_logement = :type_logement, precision_logement = :precision_logement, 
            projet_apres_formation = :projet_apres_formation, identite = :identite,
            nationalites = :nationalites
            WHERE id_personne = :id_personne";

        $params = [
            'nom' => $formData['nom'],
            'prenom' => $formData['prenom'],
            'numero_identite' => $formData['numero_identite'],
            'sexe' => $formData['sexe'],
            'date_naissance' => $formData['date_naissance'],
            'lieu_residence' => $formData['lieu_residence'],
            'etablissement' => $formData['etablissement'],
            'statut' => $formData['statut'],
            'domaine_etudes' => $formData['domaine_etudes'],
            'niveau_etudes' => $formData['niveau_etudes'],
            'telephone' => $formData['telephone'],
            'email' => $formData['email'],
            'annee_arrivee' => $formData['annee_arrivee'],
            'type_logement' => $formData['type_logement'],
            'precision_logement' => $formData['precision_logement'],
            'projet_apres_formation' => $formData['projet_apres_formation'],
            'identite' => $formData['identite'],
            'nationalites' => $nationalites_json,
            'id_personne' => $student_id
        ];

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

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
$stmt = $conn->query("SELECT region, name FROM locations ORDER BY region, name ASC");
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
    '{{type_logement_sel_En famille}}' => $sel($formData['type_logement'], 'En famille'),
    '{{type_logement_sel_En colocation}}' => $sel($formData['type_logement'], 'En colocation'),
    '{{type_logement_sel_En résidence universitaire}}' => $sel($formData['type_logement'], 'En résidence universitaire'),
    '{{type_logement_sel_Autre}}' => $sel($formData['type_logement'], 'Autre'),
    '{{statut_sel_Élève}}' => $sel($formData['statut'], 'Élève'),
    '{{statut_sel_Étudiant}}' => $sel($formData['statut'], 'Étudiant'),
    '{{statut_sel_Stagiaire}}' => $sel($formData['statut'], 'Stagiaire'),
    '{{nationalites_value}}' => htmlspecialchars($nationalites_value, ENT_QUOTES, 'UTF-8'),
];

$error_fields = ['nom', 'prenom', 'numero_identite', 'sexe', 'date_naissance', 'identite', 'telephone', 'email', 'lieu_residence', 'etablissement', 'statut', 'domaine_etudes', 'niveau_etudes', 'type_logement'];
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
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;