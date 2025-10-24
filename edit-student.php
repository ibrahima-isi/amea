<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$student_id = $_GET['id'] ?? null;

if (!$student_id) {
    header('Location: dashboard.php');
    exit();
}

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM personnes WHERE id_personne = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Handle 'Other' options
    if ($formData['etablissement'] === 'Autre') {
        $formData['etablissement'] = $formData['autre_etablissement'];
    }
    if ($formData['domaine_etudes'] === 'Autre') {
        $formData['domaine_etudes'] = $formData['autre_domaine_etudes'];
    }
    if ($formData['niveau_etudes'] === 'Autre') {
        $formData['niveau_etudes'] = $formData['autre_niveau_etudes'];
    }

    // Validation for numero_identite
    if (empty($formData['numero_identite'])) {
        $error = 'Le numéro d\'identité est requis.';
    } else {
        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE numero_identite = ? AND id_personne != ?");
        $stmt->execute([$formData['numero_identite'], $student_id]);
        if ($stmt->fetch()) {
            $error = 'Ce numéro d\'identité est déjà utilisé.';
        }
    }

    // Validate phone number
    if (empty($error) && !isValidPhone($formData['telephone'])) {
        $error = "Le numéro de téléphone doit contenir exactement 9 chiffres.";
    }

    // Handle photo upload
    $photoPath = $student['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoTmpPath = $_FILES['photo']['tmp_name'];
        $photoName = $_FILES['photo']['name'];
        $photoSize = $_FILES['photo']['size'];
        $photoType = $_FILES['photo']['type'];
        $photoExtension = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($photoExtension, $allowedExtensions)) {
            if ($photoSize < 2000000) { // 2MB
                $newFileName = uniqid('', true) . '.' . $photoExtension;
                $uploadPath = 'uploads/students/' . $newFileName;
                if (move_uploaded_file($photoTmpPath, $uploadPath)) {
                    // Delete old photo if it exists
                    if ($photoPath && file_exists($photoPath)) {
                        unlink($photoPath);
                    }
                    $photoPath = $uploadPath;
                } else {
                    $error = "Erreur lors de l'upload de l'image.";
                }
            } else {
                $error = "L'image est trop volumineuse (max 2MB).";
            }
        } else {
            $error = "Le format de l'image n'est pas supporté (jpg, jpeg, png, gif).";
        }
    }

    $formData['photo'] = $photoPath;

    if (empty($error)) {
        // Add new entries to their respective tables if they don't exist
        if (!empty($formData['etablissement'])) {
            $stmt = $conn->prepare("SELECT id FROM etablissements WHERE nom = ?");
            $stmt->execute([$formData['etablissement']]);
            if ($stmt->fetchColumn() === false) {
                $insertStmt = $conn->prepare("INSERT INTO etablissements (nom) VALUES (?)");
                $insertStmt->execute([$formData['etablissement']]);
            }
        }
        if (!empty($formData['domaine_etudes'])) {
            $stmt = $conn->prepare("SELECT id FROM domaines_etudes WHERE nom = ?");
            $stmt->execute([$formData['domaine_etudes']]);
            if ($stmt->fetchColumn() === false) {
                $insertStmt = $conn->prepare("INSERT INTO domaines_etudes (nom) VALUES (?)");
                $insertStmt->execute([$formData['domaine_etudes']]);
            }
        }
        if (!empty($formData['niveau_etudes'])) {
            $stmt = $conn->prepare("SELECT id FROM niveaux_etudes WHERE nom = ?");
            $stmt->execute([$formData['niveau_etudes']]);
            if ($stmt->fetchColumn() === false) {
                $insertStmt = $conn->prepare("INSERT INTO niveaux_etudes (nom) VALUES (?)");
                $insertStmt->execute([$formData['niveau_etudes']]);
            }
        }

        $sql = "UPDATE personnes SET 
            nom = :nom, 
            prenom = :prenom, 
            numero_identite = :numero_identite,
            sexe = :sexe, 
            date_naissance = :date_naissance, 
            lieu_residence = :lieu_residence, 
            etablissement = :etablissement, 
            statut = :statut, 
            domaine_etudes = :domaine_etudes, 
            niveau_etudes = :niveau_etudes, 
            telephone = :telephone, 
            email = :email, 
            annee_arrivee = :annee_arrivee, 
            type_logement = :type_logement, 
            precision_logement = :precision_logement, 
            projet_apres_formation = :projet_apres_formation, 
            photo = :photo
        WHERE id_personne = :id_personne";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge($formData, ['id_personne' => $student_id]));

        $success = 'Les informations de l\'étudiant ont été mises à jour avec succès.';

        // Refresh student data
        $stmt = $conn->prepare("SELECT * FROM personnes WHERE id_personne = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/edit-student.html';
if (!is_file($layoutPath) || !is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

// Fetch lists for dropdowns
$stmt_schools = $conn->query("SELECT nom FROM etablissements ORDER BY nom ASC");
$schools = $stmt_schools->fetchAll(PDO::FETCH_COLUMN);
$schools[] = 'Autre';

$stmt_domaines = $conn->query("SELECT nom FROM domaines_etudes ORDER BY nom ASC");
$domaines = $stmt_domaines->fetchAll(PDO::FETCH_COLUMN);
$domaines[] = 'Autre';

$stmt_niveaux = $conn->query("SELECT nom FROM niveaux_etudes ORDER BY nom ASC");
$niveaux = $stmt_niveaux->fetchAll(PDO::FETCH_COLUMN);
$niveaux[] = 'Autre';

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$template = file_get_contents($templatePath);

$sel = function ($value, $options) {
    return in_array($value, (array)$options) ? 'selected' : '';
};

// Generate options for dropdowns
$etablissementOptions = '';
foreach ($schools as $school) {
    $isSelected = ($student['etablissement'] ?? '') === $school;
    $etablissementOptions .= "<option value=\"$school\" " . ($isSelected ? 'selected' : '') . ">$school</option>";
}

$domaineOptions = '';
foreach ($domaines as $domaine) {
    $isSelected = ($student['domaine_etudes'] ?? '') === $domaine;
    $domaineOptions .= "<option value=\"$domaine\" " . ($isSelected ? 'selected' : '') . ">$domaine</option>";
}

$niveauOptions = '';
foreach ($niveaux as $niveau) {
    $isSelected = ($student['niveau_etudes'] ?? '') === $niveau;
    $niveauOptions .= "<option value=\"$niveau\" " . ($isSelected ? 'selected' : '') . ">$niveau</option>";
}

$contentHtml = strtr($template, [
    '{{feedback_block}}' => $success ? '<div class="alert alert-success">' . $success . '</div>' : ($error ? '<div class="alert alert-danger">' . $error . '</div>' : ''),
    '{{form_action}}' => 'edit-student.php?id=' . $student_id,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{nom}}' => htmlspecialchars($student['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($student['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{numero_identite}}' => htmlspecialchars($student['numero_identite'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{date_naissance}}' => htmlspecialchars($student['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{telephone}}' => htmlspecialchars($student['telephone'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($student['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{lieu_residence}}' => htmlspecialchars($student['lieu_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{annee_arrivee}}' => htmlspecialchars($student['annee_arrivee'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{precision_logement}}' => htmlspecialchars($student['precision_logement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{etablissement_options}}' => $etablissementOptions,
    '{{domaine_etudes_options}}' => $domaineOptions,
    '{{niveau_etudes_options}}' => $niveauOptions,
    '{{projet_apres_formation}}' => htmlspecialchars($student['projet_apres_formation'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{sexe_sel_Masculin}}' => $sel('Masculin', $student['sexe']),
    '{{sexe_sel_Féminin}}' => $sel('Féminin', $student['sexe']),
    '{{type_logement_sel_En famille}}' => $sel('En famille', $student['type_logement']),
    '{{type_logement_sel_En colocation}}' => $sel('En colocation', $student['type_logement']),
    '{{type_logement_sel_En résidence universitaire}}' => $sel('En résidence universitaire', $student['type_logement']),
    '{{type_logement_sel_Autre}}' => $sel('Autre', $student['type_logement']),
    '{{statut_sel_Élève}}' => $sel('Élève', $student['statut']),
    '{{statut_sel_Étudiant}}' => $sel('Étudiant', $student['statut']),
    '{{statut_sel_Stagiaire}}' => $sel('Stagiaire', $student['statut']),
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Modifier l\'étudiant',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;
