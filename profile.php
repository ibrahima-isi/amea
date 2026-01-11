<?php

/**
 * Admin profile page.
 * File: profile.php
 */

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
    } elseif (isset($_POST['update_profile'])) {
        $newNom = trim($_POST['nom'] ?? '');
        $newPrenom = trim($_POST['prenom'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');

        if (empty($newNom) || empty($newPrenom) || empty($newEmail)) {
            setFlashMessage('error', "Tous les champs sont obligatoires.");
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', "Veuillez entrer une adresse email valide.");
        } else {
            try {
                $checkSql = "SELECT COUNT(*) FROM users WHERE email = :email AND id_user != :id_user";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bindParam(':email', $newEmail);
                $checkStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->fetchColumn() > 0) {
                    setFlashMessage('error', "Cette adresse email est déjà utilisée par un autre utilisateur.");
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

                    setFlashMessage('success', 'Votre profil a été mis à jour avec succès.');
                }
            } catch (PDOException $e) {
                logError("Erreur lors de la mise à jour du profil utilisateur", $e);
                setFlashMessage('danger', "Une erreur est survenue lors de la mise à jour du profil. Veuillez réessayer plus tard.");
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            setFlashMessage('error', "Tous les champs de mot de passe sont obligatoires.");
        } elseif ($newPassword !== $confirmPassword) {
            setFlashMessage('error', "Le nouveau mot de passe et sa confirmation ne correspondent pas.");
        } elseif (strlen($newPassword) < 8) {
            setFlashMessage('error', "Le nouveau mot de passe doit contenir au moins 8 caractères.");
        } else {
            try {
                $sql = "SELECT password FROM users WHERE id_user = :id_user";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $user_password = $stmt->fetchColumn();

                if (password_verify($currentPassword, $user_password)) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                    $updateSql = "UPDATE users SET password = :password WHERE id_user = :id_user";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bindParam(':password', $hashedPassword);
                    $updateStmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
                    $updateStmt->execute();

                    setFlashMessage('success', 'Votre mot de passe a été changé avec succès.');
                } else {
                    setFlashMessage('error', "Le mot de passe actuel est incorrect.");
                }
            } catch (PDOException $e) {
                logError("Erreur lors du changement de mot de passe", $e);
                setFlashMessage('danger', "Une erreur est survenue lors du changement de mot de passe. Veuillez réessayer plus tard.");
            }
        }
    }
    header('Location: profile.php');
    exit();
}

// Récupérer les informations complètes de l'utilisateur
try {
    $sql = "SELECT * FROM users WHERE id_user = :id_user";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id_user', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Erreur lors de la récupération du profil utilisateur", $e);
    setFlashMessage('danger', "Impossible de récupérer les informations de profil pour le moment.");
    // Redirect or handle error appropriately
}

$csrfToken = generateCsrfToken();

// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/profile.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

// Contenu spécifique
$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{error_block}}' => '', // Handled by flash messages
    '{{success_block}}' => '', // Handled by flash messages
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
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_json}}' => $flash_json,
    '{{title}}' => 'AEESGS - Profil Administrateur',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
exit();

