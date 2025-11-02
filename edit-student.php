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

$errors = [];

$success = '';

$formData = [];



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: edit-student.php?id=' . $student_id);
        exit();
    }

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



    // Validation for numero_identite

    if (empty($formData['numero_identite'])) {

        $errors['numero_identite'] = 'Le numéro d\'identité est requis.';

    } else {

        $stmt = $conn->prepare("SELECT id_personne FROM personnes WHERE numero_identite = ? AND id_personne != ?");

        $stmt->execute([$formData['numero_identite'], $student_id]);

        if ($stmt->fetch()) {

            $errors['numero_identite'] = 'Ce numéro d\'identité est déjà utilisé.';

        }

    }



    // Validate phone number

    if (!isset($errors['telephone']) && !isValidPhone($formData['telephone'])) {

        $errors['telephone'] = "Le numéro de téléphone doit contenir exactement 9 chiffres.";

    }



    // Handle photo upload

    $photoPath = $student['photo'];

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {

        $photoTmpPath = $_FILES['photo']['tmp_name'];

        $photoName = $_FILES['photo']['name'];

        $photoSize = $_FILES['photo']['size'];

        $photoType = $_FILES['photo']['type'];

        $photoExtension = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));



        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

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

                    $errors['photo'] = "Erreur lors de l'upload de l'image.";

                }

            } else {

                $errors['photo'] = "L'image est trop volumineuse (max 2MB).";

            }

        } else {

            $errors['photo'] = "Le format du fichier n\'est pas supporté (jpg, jpeg, png, gif, pdf).";

        }

    }



    $formData['photo'] = $photoPath;



    if (empty($errors)) {

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



                setFlashMessage('success', 'Les informations de l\'étudiant ont été mises à jour avec succès.');



                header('Location: students.php');



                exit();

    }

}



$role = $_SESSION['role'];

$nom = $_SESSION['nom'];

$prenom = $_SESSION['prenom'];



// Rendu du template HTML
$flash = getFlashMessage();
$flash_script = '';
if ($flash) {
    $flash_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '{$flash['type']}',
                    title: 'Succès',
                    text: '{$flash['message']}',
                });
            });
        </script>
    ";
}

$validation_script = '';
if (!empty($errors)) {
    $errors_json = json_encode($errors);
    $validation_script = "<script>const validationErrors = ".$errors_json.";</script>";
}

$feedback_block = '';
if (!empty($errors)) {
    $feedback_block = '<div class="alert alert-danger">Veuillez corriger les erreurs ci-dessous.</div>';
}

$template = file_get_contents($templatePath);

$sel = function ($value, $option) {
    return $value === $option ? 'selected' : '';
};

// Generate options for dropdowns
$etablissementOptions = '';
foreach ($schools as $school) {
    $current_value = $formData['etablissement'] ?? $student['etablissement'] ?? '';
    $isSelected = $current_value === $school;
    $etablissementOptions .= "<option value=\"$school\" " . ($isSelected ? 'selected' : '') . ">$school</option>";
}

$domaineOptions = '';
foreach ($domaines as $domaine) {
    $current_value = $formData['domaine_etudes'] ?? $student['domaine_etudes'] ?? '';
    $isSelected = $current_value === $domaine;
    $domaineOptions .= "<option value=\"$domaine\" " . ($isSelected ? 'selected' : '') . ">$domaine</option>";
}

$niveauOptions = '';
foreach ($niveaux as $niveau) {
    $current_value = $formData['niveau_etudes'] ?? $student['niveau_etudes'] ?? '';
    $isSelected = $current_value === $niveau;
    $niveauOptions .= "<option value=\"$niveau\" " . ($isSelected ? 'selected' : '') . ">$niveau</option>";
}

$replacements = [
    '{{feedback_block}}' => $feedback_block,
    '{{form_action}}' => 'edit-student.php?id=' . $student_id,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{nom}}' => htmlspecialchars($formData['nom'] ?? $student['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($formData['prenom'] ?? $student['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{numero_identite}}' => htmlspecialchars($formData['numero_identite'] ?? $student['numero_identite'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{date_naissance}}' => htmlspecialchars($formData['date_naissance'] ?? $student['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{telephone}}' => htmlspecialchars($formData['telephone'] ?? $student['telephone'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? $student['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{lieu_residence}}' => htmlspecialchars($formData['lieu_residence'] ?? $student['lieu_residence'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{annee_arrivee}}' => htmlspecialchars($formData['annee_arrivee'] ?? $student['annee_arrivee'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{precision_logement}}' => htmlspecialchars($formData['precision_logement'] ?? $student['precision_logement'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{etablissement_options}}' => $etablissementOptions,
    '{{domaine_etudes_options}}' => $domaineOptions,
    '{{niveau_etudes_options}}' => $niveauOptions,
    '{{projet_apres_formation}}' => htmlspecialchars($formData['projet_apres_formation'] ?? $student['projet_apres_formation'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{sexe_sel_Masculin}}' => $sel($formData['sexe'] ?? $student['sexe'], 'Masculin'),
    '{{sexe_sel_Féminin}}' => $sel($formData['sexe'] ?? $student['sexe'], 'Féminin'),
    '{{type_logement_sel_En famille}}' => $sel($formData['type_logement'] ?? $student['type_logement'], 'En famille'),
    '{{type_logement_sel_En colocation}}' => $sel($formData['type_logement'] ?? $student['type_logement'], 'En colocation'),
    '{{type_logement_sel_En résidence universitaire}}' => $sel($formData['type_logement'] ?? $student['type_logement'], 'En résidence universitaire'),
    '{{type_logement_sel_Autre}}' => $sel($formData['type_logement'] ?? $student['type_logement'], 'Autre'),
    '{{statut_sel_Élève}}' => $sel($formData['statut'] ?? $student['statut'], 'Élève'),
    '{{statut_sel_Étudiant}}' => $sel($formData['statut'] ?? $student['statut'], 'Étudiant'),
    '{{statut_sel_Stagiaire}}' => $sel($formData['statut'] ?? $student['statut'], 'Stagiaire'),
];

$error_fields = [
    'nom', 'prenom', 'numero_identite', 'sexe', 'date_naissance', 'photo', 'telephone', 'email',
    'lieu_residence', 'etablissement', 'statut', 'domaine_etudes', 'niveau_etudes', 'type_logement'
];

foreach ($error_fields as $field) {
    $replacements["{{error_$field}}"] = $errors[$field] ?? '';
    $replacements["{{is_invalid_$field}}"] = isset($errors[$field]) ? 'is-invalid' : '';
}

$contentHtml = strtr($template, $replacements);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Modifier l\'étudiant',
    '{{sidebar}}' => $sidebarHtml,
    '{{flash_script}}' => $flash_script,
    '{{validation_script}}' => $validation_script,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;
