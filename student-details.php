<?php

/**
 * Page de détails d'un étudiant
 * Fichier: student-details.php
 */

// Démarrer la session
require_once 'config/session.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Récupérer les informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Vérifier si un ID étudiant est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Rediriger vers le tableau de bord s'il n'y a pas d'ID
    header("Location: dashboard.php");
    exit();
}

$student_id = (int)$_GET['id'];

// Récupérer les détails de l'étudiant depuis la base de données
try {
    $sql = "SELECT * FROM personnes WHERE id_personne = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        // L'étudiant n'existe pas, rediriger vers le tableau de bord
        setFlashMessage('error', 'L\'étudiant demandé n\'existe pas.');
        header("Location: dashboard.php");
        exit();
    }

    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En cas d'erreur, afficher un message d'erreur
    setFlashMessage('error', 'Erreur lors de la récupération des détails de l\'étudiant: ' . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}

// Titre de la page
// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/student-details.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

// Prepare flash message
$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

// Prepare validation script (always empty for a display page)
$validation_script = '';

// Read content template
$template = file_get_contents($contentPath);

// Build the details block HTML
$detailsHtml = '<div class="card"><div class="card-body">';
$detailsHtml .= '<div class="row">';
$detailsHtml .= '<div class="col-md-3 text-center">';

$identitePath = $student['identite'] ?? '';
$modalHtml = '';
if (!empty($identitePath)) {
    $fileExtension = strtolower(pathinfo($identitePath, PATHINFO_EXTENSION));
    $isPdf = ($fileExtension === 'pdf');
    $isImage = in_array($fileExtension, ['png', 'jpg', 'jpeg', 'gif']);
    $modalId = 'identiteModal' . $student['id_personne'];

    if ($isImage) {
        $detailsHtml .= '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">';
        $detailsHtml .= '<img src="' . htmlspecialchars($identitePath) . '" alt="Pièce d\'identité" class="img-fluid rounded mb-3">';
        $detailsHtml .= '</a>';
        $modalHtml = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><img src="' . htmlspecialchars($identitePath) . '" class="img-fluid"></div></div></div></div>';
    } elseif ($isPdf) {
        $detailsHtml .= '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $modalId . '" class="text-decoration-none d-block">';
        $detailsHtml .= '<i class="fas fa-file-pdf fa-5x text-danger mb-2"></i>';
        $detailsHtml .= '<span>Voir le PDF</span>';
        $detailsHtml .= '</a>';
        $modalHtml = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content" style="height: 90vh;"><div class="modal-header"><h5 class="modal-title">Pièce d\'identité</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><iframe src="' . htmlspecialchars($identitePath) . '" width="100%" height="100%"></iframe></div></div></div></div>';
    } else {
        $detailsHtml .= '<img src="assets/img/placeholder.png" alt="Pièce d\'identité" class="img-fluid rounded mb-3">';
    }
} else {
    $detailsHtml .= '<img src="assets/img/placeholder.png" alt="Pièce d\'identité" class="img-fluid rounded mb-3">';
}

$detailsHtml .= '</div>';
$detailsHtml .= '<div class="col-md-9">';
$detailsHtml .= '<h4 class="mb-3">Informations Personnelles</h4>';
$detailsHtml .= '<dl class="row">';
$detailsHtml .= '<dt class="col-sm-4">Nom Complet</dt><dd class="col-sm-8">' . htmlspecialchars(($student['prenom'] ?? '') . ' ' . ($student['nom'] ?? '')) . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Âge</dt><dd class="col-sm-8">' . calculateAge($student['date_naissance']) . ' ans</dd>';
$detailsHtml .= '<dt class="col-sm-4">Date de Naissance</dt><dd class="col-sm-8">' . formatDateFr($student['date_naissance']) . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Sexe</dt><dd class="col-sm-8">' . htmlspecialchars($student['sexe'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Numéro d\'ID/Passeport</dt><dd class="col-sm-8">' . htmlspecialchars($student['numero_identite'] ?? '') . '</dd>';
$detailsHtml .= '</dl>';
$detailsHtml .= '<hr>';
$detailsHtml .= '<h4 class="mb-3">Contact et Résidence</h4>';
$detailsHtml .= '<dl class="row">';
$detailsHtml .= '<dt class="col-sm-4">Téléphone</dt><dd class="col-sm-8">' . htmlspecialchars($student['telephone'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Email</dt><dd class="col-sm-8">' . htmlspecialchars($student['email'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Lieu de Résidence</dt><dd class="col-sm-8">' . htmlspecialchars($student['lieu_residence'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Année d\'arrivée</dt><dd class="col-sm-8">' . htmlspecialchars($student['annee_arrivee'] ?? 'N/A') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Type de Logement</dt><dd class="col-sm-8">' . htmlspecialchars($student['type_logement'] ?? '') . '</dd>';
if (!empty($student['precision_logement'])) {
    $detailsHtml .= '<dt class="col-sm-4">Précision Logement</dt><dd class="col-sm-8">' . htmlspecialchars($student['precision_logement'] ?? '') . '</dd>';
}
$detailsHtml .= '</dl>';
$detailsHtml .= '<hr>';
$detailsHtml .= '<h4 class="mb-3">Informations Académiques</h4>';
$detailsHtml .= '<dl class="row">';
$detailsHtml .= '<dt class="col-sm-4">Établissement</dt><dd class="col-sm-8">' . htmlspecialchars($student['etablissement'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Statut</dt><dd class="col-sm-8">' . htmlspecialchars($student['statut'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Domaine d\'Études</dt><dd class="col-sm-8">' . htmlspecialchars($student['domaine_etudes'] ?? '') . '</dd>';
$detailsHtml .= '<dt class="col-sm-4">Niveau d\'Études</dt><dd class="col-sm-8">' . htmlspecialchars($student['niveau_etudes'] ?? '') . '</dd>';
$detailsHtml .= '</dl>';
if (!empty($student['projet_apres_formation'])) {
    $detailsHtml .= '<hr>';
    $detailsHtml .= '<h4 class="mb-3">Projet Après Formation</h4>';
    $detailsHtml .= '<p>' . nl2br(htmlspecialchars($student['projet_apres_formation'] ?? '')) . '</p>';
}
$detailsHtml .= '</div>'; // col-md-9
$detailsHtml .= '</div>'; // row
$detailsHtml .= '</div></div>'; // card-body, card
$detailsHtml .= $modalHtml; // Append the modal HTML

// Prepare final replacements
$replacements = [
    '{{flash_block}}' => '',
    '{{details_block}}' => $detailsHtml,
    '{{student_id}}' => $student_id,
    '{{display_name}}' => htmlspecialchars(($student['prenom'] ?? '') . ' ' . ($student['nom'] ?? '')),
    '{{email}}' => htmlspecialchars($student['email'] ?? ''),
    '{{csrf_token}}' => generateCsrfToken()
];

// Perform content replacement
$contentHtml = strtr($template, $replacements);

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_json}}' => $flash_json,
    '{{title}}' => 'AEESGS - Détails de ' . htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8'),
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{validation_script}}' => $validation_script,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;
exit();
