<?php

/**
 * Page de gestion des utilisateurs
 * Fichier: users.php
 */

// Démarrer la session
require_once 'config/session.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Initialiser les variables
$message = "";
$messageType = "";
$users = [];
$csrfToken = generateCsrfToken();

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
        $message = "La session a expiré. Veuillez réessayer.";
        $messageType = "warning";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        switch ($action) {
            case 'delete':
                if ($userId !== (int)$_SESSION['user_id']) {
                    try {
                        $sql = "DELETE FROM users WHERE id_user = :id_user";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':id_user', $userId, PDO::PARAM_INT);
                        $stmt->execute();

                        $message = "L'utilisateur a été supprimé avec succès.";
                        $messageType = "success";
                    } catch (PDOException $e) {
                        logError("Erreur lors de la suppression d'un utilisateur", $e);
                        $message = "Une erreur est survenue lors de la suppression de l'utilisateur.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Vous ne pouvez pas supprimer votre propre compte.";
                    $messageType = "warning";
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
                        $message = "L'utilisateur a été " . $statusText . " avec succès.";
                        $messageType = "success";
                    } catch (PDOException $e) {
                        logError("Erreur lors du changement de statut d'un utilisateur", $e);
                        $message = "Une erreur est survenue lors de la modification du statut.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Vous ne pouvez pas désactiver votre propre compte.";
                    $messageType = "warning";
                }
                break;



            default:
                $message = "Action non reconnue.";
                $messageType = "warning";
                break;
        }
    }

    $csrfToken = generateCsrfToken();
}

// Récupérer la liste des utilisateurs
$users = getUsers($conn);

// Sécuriser le type de message affiché
$allowedMessageTypes = ['success', 'danger', 'warning', 'info'];
if (!in_array($messageType, $allowedMessageTypes, true)) {
    $messageType = 'info';
}

// Titre de la page
// Rendu via layout + contenu
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/users.html';
if (!is_file($layoutPath) || !is_file($contentPath)) { http_response_code(500); exit('Template introuvable.'); }

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

// Message block
$messageBlock = '';
if (!empty($message)) {
    $messageBlock = '<div class="alert alert-' . htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') . '">'
        . '<i class="fas fa-info-circle"></i> ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</div>';
}

// Rows
$rows = '';
foreach ($users as $user) {
    $rows .= '<tr>'
        . '<td>' . (int)$user['id_user'] . '</td>'
        . '<td>' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['nom'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['prenom'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td>' . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td><span class="badge ' . ($user['role'] == 'admin' ? 'bg-success' : 'bg-secondary') . '">' . htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') . '</span></td>'
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
    '{{message_block}}' => $messageBlock,
    '{{users_rows}}' => $rows,
]);

// Layout
$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Gestion des utilisateurs',
    '{{sidebar}}' => $sidebarHtml,
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

