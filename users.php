<?php

/**
 * Page de gestion des utilisateurs
 * Fichier: users.php
 */

require_once 'config/session.php';
require_once 'functions/utility-functions.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    setFlashMessage('error', 'Accès non autorisé.');
    header("Location: dashboard.php");
    exit();
}

$role = $_SESSION['role'];

// Inclure la configuration de la base de données
require_once 'config/database.php';

// Fonction pour obtenir la liste des utilisateurs
function getUsers($conn)
{
    try {
        $sql = "SELECT id_user, username, nom, prenom, email, role, est_actif, derniere_connexion, date_creation
                FROM users ORDER BY id_user DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError('Erreur lors de la récupération des utilisateurs', $e);
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('warning', 'La session a expiré. Veuillez réessayer.');
    } else {
        $action = $_POST['action'] ?? '';
        $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        switch ($action) {
            case 'reset_password':
                if ($userId !== (int)$_SESSION['user_id']) {
                    try {
                        $stmt = $conn->prepare("SELECT role, username FROM users WHERE id_user = :id_user");
                        $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                        $stmt->execute();
                        $userToReset = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($userToReset && $userToReset['role'] !== 'admin') {
                            $newPassword = bin2hex(random_bytes(8));
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                            $sql = "UPDATE users SET password = :password WHERE id_user = :id_user";
                            $stmt = $conn->prepare($sql);
                            $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
                            $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                            $stmt->execute();

                            $successMessage = "Le mot de passe pour l'utilisateur <strong>" . htmlspecialchars($userToReset['username'], ENT_QUOTES, 'UTF-8') . "</strong> a été réinitialisé.<br>Le nouveau mot de passe est : <code>" . htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8') . "</code>";
                            setFlashMessage('success', $successMessage);
                        } else {
                            setFlashMessage('warning', "Vous ne pouvez pas réinitialiser le mot de passe d'un administrateur.");
                        }
                    } catch (PDOException $e) {
                        logError("Erreur lors de la réinitialisation du mot de passe d'un utilisateur", $e);
                        setFlashMessage('danger', "Une erreur est survenue lors de la réinitialisation du mot de passe.");
                    }
                } else {
                    setFlashMessage('warning', "Vous ne pouvez pas réinitialiser votre propre mot de passe via cette méthode.");
                }
                break;

            case 'delete':
                if ($userId !== (int)$_SESSION['user_id']) {
                    try {
                        $sql = "DELETE FROM users WHERE id_user = :id_user";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                        $stmt->execute();
                        setFlashMessage('success', "L'utilisateur a été supprimé avec succès.");
                    } catch (PDOException $e) {
                        logError("Erreur lors de la suppression d'un utilisateur", $e);
                        setFlashMessage('danger', "Une erreur est survenue lors de la suppression de l'utilisateur.");
                    }
                } else {
                    setFlashMessage('warning', "Vous ne pouvez pas supprimer votre propre compte.");
                }
                break;

            case 'toggle':
                $currentStatus = isset($_POST['status']) ? (int)$_POST['status'] : 0;
                $newStatus = $currentStatus ? 0 : 1;

                if ($userId !== (int)$_SESSION['user_id'] || $newStatus === 1) {
                    try {
                        $sql = "UPDATE users SET est_actif = :est_actif WHERE id_user = :id_user";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':est_actif', $newStatus, PDO::PARAM_INT);
                        $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                        $stmt->execute();

                        $statusText = $newStatus ? "activé" : "désactivé";
                        setFlashMessage('success', "L'utilisateur a été " . $statusText . " avec succès.");
                    } catch (PDOException $e) {
                        logError("Erreur lors du changement de statut d'un utilisateur", $e);
                        setFlashMessage('danger', "Une erreur est survenue lors de la modification du statut.");
                    }
                } else {
                    setFlashMessage('warning', "Vous ne pouvez pas désactiver votre propre compte.");
                }
                break;

            default:
                setFlashMessage('warning', "Action non reconnue.");
                break;
        }
    }

    header("Location: users.php");
    exit();
}

// Récupérer la liste des utilisateurs
$users = getUsers($conn);
$csrfToken = generateCsrfToken();

// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/users.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

// Rows
$rows = '';
foreach ($users as $user) {
    $role_display = ($user['role'] === 'admin') ? 'Administrateur' : 'Utilisateur';
    $rows .= '<tr>'
        . '<td>' . (int)$user['id_user'] . '</td>'
        . '<td>' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['nom'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['prenom'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td><span class="badge ' . ($user['role'] == 'admin' ? 'bg-success' : 'bg-secondary') . '">' . htmlspecialchars($role_display, ENT_QUOTES, 'UTF-8') . '</span></td>'
        . '<td><span class="badge ' . ($user['est_actif'] ? 'bg-success' : 'bg-danger') . '">' . ($user['est_actif'] ? 'Actif' : 'Inactif') . '</span></td>'
        . '<td>' . htmlspecialchars($user['derniere_connexion'] ?? 'Jamais', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td><div class="btn-group" role="group">'
            . '<form method="POST" class="d-inline">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="action" value="toggle">'
            . '<input type="hidden" name="id" value="' . (int)$user['id_user'] . '">'
            . '<input type="hidden" name="status" value="' . (int)$user['est_actif'] . '">'
            . '<button type="submit" class="btn btn-sm btn-warning" title="' . ($user['est_actif'] ? 'Désactiver' : 'Activer') . '">'
            . '<i class="fas ' . ($user['est_actif'] ? 'fa-ban' : 'fa-check') . '"></i>'
            . '</button>'
            . '</form>'
            . '<a href="edit-user.php?id=' . (int)$user['id_user'] . '" class="btn btn-sm btn-info" title="Modifier">
                <i class="fas fa-edit"></i>
            </a>'
            . (($user['role'] !== 'admin') ? (
                '<form method="POST" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="action" value="reset_password">'
                . '<input type="hidden" name="id" value="' . (int)$user['id_user'] . '">'
                . '<button type="submit" class="btn btn-sm btn-secondary btn-reset-password" title="Réinitialiser le mot de passe">'
                . '<i class="fas fa-key"></i>'
                . '</button>'
                . '</form>'
            ) : '')
    . (($user['id_user'] != $_SESSION['user_id']) ? (
                '<form method="POST" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="id" value="' . (int)$user['id_user'] . '">'
                . '<button type="submit" class="btn btn-sm btn-danger btn-delete-user" title="Supprimer">'
                . '<i class="fas fa-trash"></i>'
                . '</button>'
                . '</form>'
            ) : '')
            . '</div></td>'
        . '</tr>';
}

// Contenu
$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{message_block}}' => '', // Message block is now handled by flash messages
    '{{users_rows}}' => $rows,
]);

// Layout
$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Gestion des utilisateurs',
    '{{sidebar}}' => $sidebarHtml,
    '{{flash_json}}' => $flash_json,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom'], ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;
exit();
