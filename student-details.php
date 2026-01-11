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
$detailsHtml = '<div class="row">';

// --- LEFT COLUMN: Profile Card & Quick Contact ---
$detailsHtml .= '<div class="col-lg-4 mb-4">';

// Profile Card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-body text-center pt-4">';

$identitePath = $student['identite'] ?? '';
$modalHtml = '';
$modalId = 'identiteModal' . $student['id_personne'];

if (!empty($identitePath)) {
    $fileExtension = strtolower(pathinfo($identitePath, PATHINFO_EXTENSION));
    $isPdf = ($fileExtension === 'pdf');
    $isImage = in_array($fileExtension, ['png', 'jpg', 'jpeg', 'gif']);

    if ($isImage) {
        $detailsHtml .= '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">';
        $detailsHtml .= '<img src="' . htmlspecialchars($identitePath) . '" alt="Photo de profil" class="rounded-circle img-thumbnail mb-3" style="width: 150px; height: 150px; object-fit: cover;">';
        $detailsHtml .= '</a>';
        $modalHtml = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-body text-center"><img src="' . htmlspecialchars($identitePath) . '" class="img-fluid"></div></div></div></div>';
    } elseif ($isPdf) {
        $detailsHtml .= '<a href="#" data-bs-toggle="modal" data-bs-target="#' . $modalId . '" class="d-inline-block mb-3 text-decoration-none">';
        $detailsHtml .= '<div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">';
        $detailsHtml .= '<i class="fas fa-file-pdf fa-4x text-danger"></i>';
        $detailsHtml .= '</div>';
        $detailsHtml .= '<span class="d-block mt-2 small text-muted">Voir le document</span>';
        $detailsHtml .= '</a>';
        $modalHtml = '<div class="modal fade" id="' . $modalId . '" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content" style="height: 90vh;"><div class="modal-header"><h5 class="modal-title">Document d\'identité</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><iframe src="' . htmlspecialchars($identitePath) . '" width="100%" height="100%"></iframe></div></div></div></div>';
    } else {
         $detailsHtml .= '<img src="assets/img/placeholder.png" alt="Profil" class="rounded-circle img-thumbnail mb-3" style="width: 150px; height: 150px; object-fit: cover;">';
    }
} else {
    $detailsHtml .= '<img src="assets/img/placeholder.png" alt="Profil" class="rounded-circle img-thumbnail mb-3" style="width: 150px; height: 150px; object-fit: cover;">';
}

$fullName = ($student['prenom'] ?? '') . ' ' . ($student['nom'] ?? '');
$detailsHtml .= '<h4 class="card-title mb-1">' . htmlspecialchars($fullName) . '</h4>';

// Status Badge
$statusColor = 'secondary';
if (($student['statut'] ?? '') === 'Étudiant') $statusColor = 'primary';
elseif (($student['statut'] ?? '') === 'Élève') $statusColor = 'info';
$detailsHtml .= '<span class="badge bg-' . $statusColor . ' mb-3">' . htmlspecialchars($student['statut'] ?? 'Inconnu') . '</span>';

// Quick Stat Row (Age, Gender)
$detailsHtml .= '<div class="d-flex justify-content-center gap-3 text-muted">';
$detailsHtml .= '<div><i class="fas fa-venus-mars me-1"></i> ' . htmlspecialchars($student['sexe'] ?? '?') . '</div>';
$detailsHtml .= '<div><i class="fas fa-birthday-cake me-1"></i> ' . calculateAge($student['date_naissance']) . ' ans</div>';
$detailsHtml .= '</div>';

$detailsHtml .= '</div></div>'; // End Profile Card

// Contact Card
$detailsHtml .= '<div class="card shadow-sm border-0">';
$detailsHtml .= '<div class="card-header bg-white border-0 pt-3 pb-0"><h6 class="fw-bold text-uppercase text-muted small"><i class="fas fa-address-book me-2"></i>Coordonnées</h6></div>';
$detailsHtml .= '<div class="card-body">';
$detailsHtml .= '<ul class="list-unstyled mb-0">';
$detailsHtml .= '<li class="mb-3 d-flex align-items-start"><i class="fas fa-envelope text-primary mt-1 me-3"></i><div><span class="d-block small text-muted">Email</span><a href="mailto:' . htmlspecialchars($student['email'] ?? '') . '" class="text-dark text-decoration-none">' . htmlspecialchars($student['email'] ?? 'N/A') . '</a></div></li>';
$detailsHtml .= '<li class="mb-3 d-flex align-items-start"><i class="fas fa-phone text-success mt-1 me-3"></i><div><span class="d-block small text-muted">Téléphone</span><a href="tel:' . htmlspecialchars($student['telephone'] ?? '') . '" class="text-dark text-decoration-none">' . htmlspecialchars($student['telephone'] ?? 'N/A') . '</a></div></li>';
$detailsHtml .= '<li class="d-flex align-items-start"><i class="fas fa-map-marker-alt text-danger mt-1 me-3"></i><div><span class="d-block small text-muted">Lieu de résidence</span>' . htmlspecialchars($student['lieu_residence'] ?? 'N/A') . '</div></li>';
$detailsHtml .= '</ul>';
$detailsHtml .= '</div></div>'; // End Contact Card

$detailsHtml .= '</div>'; // End Left Column

// --- RIGHT COLUMN: Academic & Details ---
$detailsHtml .= '<div class="col-lg-8">';

// Academic Info Card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-primary"><i class="fas fa-graduation-cap me-2"></i>Parcours Académique</h5></div>';
$detailsHtml .= '<div class="card-body">';
$detailsHtml .= '<div class="row g-4">';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Établissement</small><span class="fw-bold text-dark">' . htmlspecialchars($student['etablissement'] ?? 'Non spécifié') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Domaine d\'Études</small><span class="fw-bold text-dark">' . htmlspecialchars($student['domaine_etudes'] ?? 'Non spécifié') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Niveau d\'Études</small><span class="fw-bold text-dark">' . htmlspecialchars($student['niveau_etudes'] ?? 'Non spécifié') . '</span></div></div>';
$detailsHtml .= '<div class="col-md-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block mb-1">Année d\'arrivée</small><span class="fw-bold text-dark">' . htmlspecialchars($student['annee_arrivee'] ?? 'N/A') . '</span></div></div>';
$detailsHtml .= '</div>';
$detailsHtml .= '</div></div>';

// Personal Details Card
$detailsHtml .= '<div class="card shadow-sm border-0 mb-4">';
$detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>Informations Personnelles</h5></div>';
$detailsHtml .= '<div class="card-body">';
$detailsHtml .= '<div class="row mb-3">';
$detailsHtml .= '<div class="col-md-6 mb-3"><strong class="d-block text-muted small">Nationalités</strong>';
// Nationalities
if (!empty($student['nationalites'])) {
    $nats = json_decode($student['nationalites'], true);
    if (is_array($nats) && count($nats) > 0) {
        foreach($nats as $nat) {
            $detailsHtml .= '<span class="badge bg-secondary me-1">' . htmlspecialchars($nat, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    } else {
        $detailsHtml .= '<span class="text-muted">N/A</span>';
    }
} else {
    $detailsHtml .= '<span class="text-muted">N/A</span>';
}
$detailsHtml .= '</div>';
$detailsHtml .= '<div class="col-md-6 mb-3"><strong class="d-block text-muted small">Date de Naissance</strong>' . formatDateFr($student['date_naissance']) . '</div>';
$detailsHtml .= '<div class="col-md-6 mb-3"><strong class="d-block text-muted small">Numéro ID / Passeport</strong>' . htmlspecialchars($student['numero_identite'] ?? 'N/A') . '</div>';
$detailsHtml .= '<div class="col-md-6 mb-3"><strong class="d-block text-muted small">Type de Logement</strong>' . htmlspecialchars($student['type_logement'] ?? 'N/A') . '</div>';
if (!empty($student['precision_logement'])) {
    $detailsHtml .= '<div class="col-12"><strong class="d-block text-muted small">Précision Logement</strong>' . htmlspecialchars($student['precision_logement']) . '</div>';
}
$detailsHtml .= '</div>';
$detailsHtml .= '</div></div>';

// Project Card (if exists)
if (!empty($student['projet_apres_formation'])) {
    $detailsHtml .= '<div class="card shadow-sm border-0">';
    $detailsHtml .= '<div class="card-header bg-transparent border-bottom py-3"><h5 class="mb-0 text-primary"><i class="fas fa-rocket me-2"></i>Projet Professionnel</h5></div>';
    $detailsHtml .= '<div class="card-body">';
    $detailsHtml .= '<p class="card-text text-dark" style="line-height: 1.6;">' . nl2br(htmlspecialchars($student['projet_apres_formation'])) . '</p>';
    $detailsHtml .= '</div></div>';
}

$detailsHtml .= '</div>'; // End Right Column
$detailsHtml .= '</div>'; // End Row
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
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo $output;
exit();
