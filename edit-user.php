<?php

/**
 * Edit user page.
 * File: edit-user.php
 */

require_once 'config/session.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    setFlashMessage('error', 'Accès non autorisé.');
    header('Location: dashboard.php');
    exit();
}

require_once 'config/database.php';

$user_id_to_edit = $_GET['id'] ?? null;

if (!$user_id_to_edit) {
    header('Location: users.php');
    exit();
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$user_id_to_edit]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit();
}

if (!hasPermission('users')) {
    setFlashMessage('error', 'Accès refusé : vous n\'avez pas la permission de modifier les utilisateurs.');
    header('Location: dashboard.php');
    exit();
}

// Security: Only Super Admin (ID 1) can edit Super Admin (ID 1)
if ((int)$user_id_to_edit === 1 && (int)$_SESSION['user_id'] !== 1) {
    setFlashMessage('error', 'Accès refusé : seul le Super Administrateur peut modifier son propre compte.');
    header('Location: users.php');
    exit();
}

// Security: Non-super-admins cannot modify their own permissions (privilege escalation prevention)
$is_editing_self = ((int)$user_id_to_edit === (int)$_SESSION['user_id']);
$is_super_admin_session = ((int)$_SESSION['user_id'] === 1);

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: edit-user.php?id=' . $user_id_to_edit);
        exit();
    }
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? 'user',
        'est_actif' => $_POST['est_actif'] ?? 0,
        'permissions' => $_POST['permissions'] ?? []
    ];

    if (empty($formData['username'])) {
        $errors['username'] = 'Le nom d\'utilisateur est requis.';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'L\'adresse email est requise.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'L\'adresse email n\'est pas valide.';
    }

    if (empty($errors)) {
        // Convert permissions to JSON string for storage.
        // Whitelist submitted values against known modules to prevent arbitrary strings
        // from being persisted in the DB.
        $knownModules = ['students','export','users','slider','upgrade','documents','communications','settings'];
        $permissionsJson = null;
        if ($formData['role'] === 'admin') {
            // Non-super-admins cannot modify their own permissions (privilege escalation prevention)
            if ($is_editing_self && !$is_super_admin_session) {
                $permissionsJson = $user['permissions']; // restore from DB, ignore POST
            } else {
                $permissionsJson = json_encode(array_values(array_intersect($formData['permissions'], $knownModules)));
            }
        }

        // Safety: ensure User ID 1 (Super Admin) always has all permissions in the DB
        if ((int)$user_id_to_edit === 1) {
            $permissionsJson = json_encode(["students", "export", "users", "slider", "upgrade", "documents", "communications", "settings"]);
        }

        $sql = "UPDATE users SET 
            username = :username, 
            email = :email, 
            role = :role, 
            permissions = :permissions,
            est_actif = :est_actif
        WHERE id_user = :id_user";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'username' => $formData['username'],
            'email' => $formData['email'],
            'role' => $formData['role'],
            'permissions' => $permissionsJson,
            'est_actif' => $formData['est_actif'],
            'id_user' => $user_id_to_edit
        ]);

        setFlashMessage('success', 'Les informations de l\'utilisateur ont été mises à jour avec succès.');
        header('Location: users.php');
        exit();
    }
}

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/edit-user.html';
if (!is_file($layoutPath) || !is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$template = file_get_contents($templatePath);

$sel = function ($value, $options) {
    return in_array($value, (array)$options) ? 'selected' : '';
};

$validation_errors_json = '';
if (!empty($errors)) {
    $validation_errors_json = json_encode($errors);
}

$userPermissions = $formData['permissions'] ?? json_decode($user['permissions'] ?? '[]', true) ?? [];
$isSuperAdmin = ((int)$user_id_to_edit === 1);

$contentHtml = strtr($template, [
    '{{feedback_block}}' => '',
    '{{form_action}}' => 'edit-user.php?id=' . $user_id_to_edit,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{username}}' => htmlspecialchars($formData['username'] ?? $user['username'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? $user['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{role_sel_admin}}' => $sel('admin', $formData['role'] ?? $user['role']),
    '{{role_sel_user}}' => $sel('user', $formData['role'] ?? $user['role']),
    '{{est_actif_sel_1}}' => $sel(1, $formData['est_actif'] ?? $user['est_actif']),
    '{{est_actif_sel_0}}' => $sel(0, $formData['est_actif'] ?? $user['est_actif']),
    '{{error_username}}' => $errors['username'] ?? '',
    '{{error_email}}' => $errors['email'] ?? '',
    '{{is_invalid_username}}' => isset($errors['username']) ? 'is-invalid' : '',
    '{{is_invalid_email}}' => isset($errors['email']) ? 'is-invalid' : '',
    '{{permissions_display}}' => ($formData['role'] ?? $user['role']) === 'admin' ? 'block' : 'none',
    '{{perm_students_checked}}' => in_array('students', $userPermissions) ? 'checked' : '',
    '{{perm_export_checked}}' => in_array('export', $userPermissions) ? 'checked' : '',
    '{{perm_users_checked}}' => in_array('users', $userPermissions) ? 'checked' : '',
    '{{perm_slider_checked}}' => in_array('slider', $userPermissions) ? 'checked' : '',
    '{{perm_upgrade_checked}}' => in_array('upgrade', $userPermissions) ? 'checked' : '',
    '{{perm_documents_checked}}' => in_array('documents', $userPermissions) ? 'checked' : '',
    '{{perm_communications_checked}}' => in_array('communications', $userPermissions) ? 'checked' : '',
    '{{perm_settings_checked}}' => in_array('settings', $userPermissions) ? 'checked' : '',
    '{{permissions_disabled}}' => $isSuperAdmin ? 'disabled onclick="return false;"' : '',
    '{{super_admin_badge}}' => $isSuperAdmin ? '<span class="badge bg-danger">Super Admin</span>' : '',
]);

$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_json}}' => $flash_json,
    '{{validation_errors_json}}' => $validation_errors_json,
    '{{title}}' => 'AEESGS - Modifier l\'utilisateur',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
