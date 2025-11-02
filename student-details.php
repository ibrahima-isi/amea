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

$flash = getFlashMessage();
$flash_script = '';
if ($flash) {
    $flash_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '{$flash['type']}',
                    title: 'Notification',
                    text: '{$flash['message']}',
                });
            });
        </script>
    ";
}

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_script}}' => $flash_script,
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
