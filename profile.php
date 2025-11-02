<?php

/**
 * Page de profil administrateur
 * Fichier: profile.php
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

// Initialiser les variables d'erreur et de succès
$error = "";
$success = "";

// Récupérer les informations complètes de l'utilisateur
try {
    $sql = "SELECT * FROM users WHERE id_user = :id_user";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Erreur lors de la récupération du profil utilisateur", $e);
    $error = "Impossible de récupérer les informations de profil pour le moment.";
}

$csrfToken = generateCsrfToken();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: profile.php');
        exit();
    } elseif (isset($_POST['update_profile'])) {
        $newNom = trim($_POST['nom'] ?? '');
        $newPrenom = trim($_POST['prenom'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');

        if (empty($newNom) || empty($newPrenom) || empty($newEmail)) {
            $error = "Tous les champs sont obligatoires.";
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez entrer une adresse email valide.";
        } else {
            try {
                $checkSql = "SELECT COUNT(*) FROM users WHERE email = :email AND id_user != :id_user";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bindParam(':email', $newEmail);
                $checkStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    $error = "Cette adresse email est déjà utilisée par un autre utilisateur.";
                } else {
                    $updateSql = "UPDATE users SET nom = :nom, prenom = :prenom, email = :email WHERE id_user = :id_user";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':nom', $newNom);
                    $updateStmt->bindParam(':prenom', $newPrenom);
                    $updateStmt->bindParam(':email', $newEmail);
                    $updateStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    $_SESSION['nom'] = $newNom;
                    $_SESSION['prenom'] = $newPrenom;

                    $sql = "SELECT * FROM users WHERE id_user = :id_user";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    setFlashMessage('success', 'Votre profil a été mis à jour avec succès.');
                    header('Location: profile.php');
                    exit();
                }
            } catch (PDOException $e) {
                logError("Erreur lors de la mise à jour du profil utilisateur", $e);
                $error = "Une erreur est survenue lors de la mise à jour du profil. Veuillez réessayer plus tard.";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "Tous les champs de mot de passe sont obligatoires.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        } elseif (strlen($newPassword) < 8) {
            $error = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } else {
            try {
                if (password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $updateSql = "UPDATE users SET password = :password WHERE id_user = :id_user";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':password', $hashedPassword);
                    $updateStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    setFlashMessage('success', 'Votre mot de passe a été changé avec succès.');
                    header('Location: profile.php');
                    exit();
                } else {
                    $error = "Le mot de passe actuel est incorrect.";
                }
            } catch (PDOException $e) {
                logError("Erreur lors du changement de mot de passe", $e);
                $error = "Une erreur est survenue lors du changement de mot de passe. Veuillez réessayer plus tard.";
            }
        }
    }

    $csrfToken = generateCsrfToken();
}

// Titre de la page
// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/profile.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

// Blocs d'alerte
$errorBlock = '';
if (!empty($error)) {
    $errorBlock = '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
        . '<i class="fas fa-exclamation-triangle me-2"></i> ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}
$successBlock = '';
if (!empty($success)) {
    $successBlock = '<div class="alert alert-success alert-dismissible fade show" role="alert">'
        . '<i class="fas fa-check-circle me-2"></i> ' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
        . '</div>';
}

// Contenu spécifique
$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{error_block}}' => $errorBlock,
    '{{success_block}}' => $successBlock,
    '{{avatar_initials}}' => htmlspecialchars(strtoupper(substr($user['prenom'] ?? '', 0, 1) . substr($user['nom'] ?? '', 0, 1)), ENT_QUOTES, 'UTF-8'),
    '{{display_name}}' => htmlspecialchars(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''), ENT_QUOTES, 'UTF-8'),
    '{{username}}' => htmlspecialchars($username, ENT_QUOTES, 'UTF-8'),
    '{{role}}' => htmlspecialchars($role, ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{nom}}' => htmlspecialchars($user['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($user['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{form_action}}' => htmlspecialchars($_SERVER['PHP_SELF'] ?? 'profile.php', ENT_QUOTES, 'UTF-8'),
    '{{csrf_token}}' => htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'),
]);

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
    '{{title}}' => 'AEESGS - Profil Administrateur',
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

