<?php

/**
 * User management page.
 * File: users.php
 */

require_once 'config/session.php';
require_once 'functions/utility-functions.php';
require_once 'functions/email-service.php';

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

if (!hasPermission('users')) {
    setFlashMessage('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
    header('Location: dashboard.php'); exit();
}

// Fonction pour obtenir la liste des utilisateurs
function getUsers($conn)
{
    try {
        $sql = "SELECT id, username, last_name, first_name, email, role, is_active, last_login, created_at
                FROM users ORDER BY id DESC";
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
                        $stmt = $conn->prepare("SELECT role, username FROM users WHERE id = :id");
                        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
                        $stmt->execute();
                        $userToReset = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$userToReset) {
                            setFlashMessage('warning', "Utilisateur introuvable.");
                        } elseif ($userId === 1 && (int)$_SESSION['user_id'] !== 1) {
                            setFlashMessage('warning', "Seul le Super Administrateur peut réinitialiser ce compte.");
                        } else {
                            $resetService = new \Amea\Service\PasswordResetService(
                                $conn,
                                (string)env('APP_URL', 'http://localhost'),
                                __DIR__
                            );

                            if ($resetService->requestForUserId($userId, 'sendMail')) {
                                setFlashMessage('success', "Un lien sécurisé de réinitialisation a été envoyé à l'adresse e-mail du compte.");
                            } else {
                                setFlashMessage('error', "Impossible d'envoyer le lien de réinitialisation. Veuillez réessayer plus tard.");
                            }
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
                if ($userId === 1) {
                    setFlashMessage('error', "Le Super Administrateur ne peut pas être supprimé.");
                } elseif ($userId !== (int)$_SESSION['user_id']) {
                    try {
                        $sql = "DELETE FROM users WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
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

                if ($userId === 1 && $newStatus === 0) {
                    setFlashMessage('error', "Le Super Administrateur ne peut pas être désactivé.");
                } elseif ($userId !== (int)$_SESSION['user_id'] || $newStatus === 1) {
                    try {
                        $sql = "UPDATE users SET is_active = :is_active WHERE id = :id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':is_active', $newStatus, PDO::PARAM_INT);
                        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
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
        . '<td>' . (int)$user['id'] . '</td>'
        . '<td>' . htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td><span class="badge ' . ($user['role'] == 'admin' ? 'bg-success' : 'bg-secondary') . '">' . htmlspecialchars($role_display, ENT_QUOTES, 'UTF-8') . '</span></td>'
        . '<td><span class="badge ' . ($user['is_active'] ? 'bg-success' : 'bg-danger') . '">' . ($user['is_active'] ? 'Actif' : 'Inactif') . '</span></td>'
        . '<td>' . htmlspecialchars($user['last_login'] ?? 'Jamais', ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td><div class="btn-group" role="group">'
            . '<form method="POST" class="d-inline">'
            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="action" value="toggle">'
            . '<input type="hidden" name="id" value="' . (int)$user['id'] . '">'
            . '<input type="hidden" name="status" value="' . (int)$user['is_active'] . '">'
            . '<button type="submit" class="btn btn-sm btn-warning" title="' . ($user['is_active'] ? 'Désactiver' : 'Activer') . '">'
            . '<i class="fas ' . ($user['is_active'] ? 'fa-ban' : 'fa-check') . '"></i>'
            . '</button>'
            . '</form>'
            . '<a href="edit-user.php?id=' . (int)$user['id'] . '" class="btn btn-sm btn-info" title="Modifier">
                <i class="fas fa-edit"></i>
            </a>'
            . (((int)$user['id'] !== (int)$_SESSION['user_id'] && (int)$user['id'] !== 1) ? (
                '<form method="POST" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="action" value="reset_password">'
                . '<input type="hidden" name="id" value="' . (int)$user['id'] . '">'
                . '<button type="submit" class="btn btn-sm btn-secondary btn-reset-password" title="Réinitialiser le mot de passe">'
                . '<i class="fas fa-key"></i>'
                . '</button>'
                . '</form>'
            ) : '')
    . (($user['id'] != $_SESSION['user_id']) ? (
                '<form method="POST" class="d-inline">'
                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="action" value="delete">'
                . '<input type="hidden" name="id" value="' . (int)$user['id'] . '">'
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
    '{{validation_errors_json}}' => '',
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
exit();
