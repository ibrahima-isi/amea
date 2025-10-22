<?php

/**
 * Page de détails d'un étudiant
 * Fichier: student-details.php
 */

// Démarrer la session
session_start();

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
    $sql = "SELECT * FROM personne WHERE id_personne = :id";
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

// Flash block
$flash = getFlashMessage();
$flashBlock = '';
if ($flash) {
    $class = getFlashMessageClass($flash['type']);
    $flashBlock = '<div class="alert ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' alert-dismissible fade show" role="alert">'
        . $flash['message'] . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

// Construire le bloc de détails
$details = '';
$details .= '<div class="row">';
$details .= '<div class="col-md-4 mb-4"><div class="card shadow h-100"><div class="card-header"><h5 class="m-0">Informations de base</h5></div><div class="card-body">'
    . '<p><strong>Nom:</strong> ' . htmlspecialchars($student['nom'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Prénom:</strong> ' . htmlspecialchars($student['prenom'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Sexe:</strong> ' . htmlspecialchars($student['sexe'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Âge:</strong> ' . htmlspecialchars($student['age'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Date de naissance:</strong> ' . htmlspecialchars($student['date_naissance'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '</div></div></div>';
$details .= '<div class="col-md-4 mb-4"><div class="card shadow h-100"><div class="card-header"><h5 class="m-0">Contact</h5></div><div class="card-body">'
    . '<p><i class="fas fa-envelope me-2"></i>' . htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><i class="fas fa-phone me-2"></i>' . htmlspecialchars($student['telephone'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Résidence:</strong> ' . htmlspecialchars($student['lieu_residence'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '</div></div></div>';
$details .= '<div class="col-md-4 mb-4"><div class="card shadow h-100"><div class="card-header"><h5 class="m-0">Parcours académique</h5></div><div class="card-body">'
    . '<p><strong>Établissement:</strong> ' . htmlspecialchars($student['etablissement'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Statut:</strong> ' . htmlspecialchars($student['statut'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Domaine d\'études:</strong> ' . htmlspecialchars($student['domaine_etudes'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '<p><strong>Niveau d\'études:</strong> ' . htmlspecialchars($student['niveau_etudes'], ENT_QUOTES, 'UTF-8') . '</p>'
    . '</div></div></div>';
$details .= '</div>';

// Contenu
$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{flash_block}}' => $flashBlock,
    '{{details_block}}' => $details,
    '{{student_id}}' => (string)$student_id,
    '{{display_name}}' => htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'),
    '{{csrf_token}}' => htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'),
]);

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Détails de ' . htmlspecialchars($student['prenom'] . ' ' . $student['nom'], ENT_QUOTES, 'UTF-8'),
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
exit();
