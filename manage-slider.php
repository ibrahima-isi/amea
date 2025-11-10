<?php

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

$uploadDir = 'uploads/slider/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: manage-slider.php');
        exit();
    }

    $action = $_POST['action'];

    try {
        if ($action === 'add' || $action === 'edit') {
            $title = trim($_POST['title'] ?? '');
            $caption = trim($_POST['caption'] ?? '');
            $display_order = (int)($_POST['display_order'] ?? 0);
            $is_active = (int)($_POST['is_active'] ?? 0);
            $image_id = (int)($_POST['image_id'] ?? 0);

            $image_path = '';
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $fileName = uniqid('', true) . '_' . basename($_FILES['image_file']['name']);
                $targetFilePath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $targetFilePath)) {
                    $image_path = $targetFilePath;
                } else {
                    throw new Exception("Erreur lors du téléchargement de l'image.");
                }
            }

            if ($action === 'add') {
                if (empty($image_path)) throw new Exception("Le fichier image est requis.");
                $stmt = $conn->prepare(
                    "INSERT INTO slider_images (image_path, title, caption, display_order, is_active, uploaded_by) 
                     VALUES (:image_path, :title, :caption, :display_order, :is_active, :uploaded_by)"
                );
                $stmt->execute([
                    ':image_path' => $image_path,
                    ':title' => $title,
                    ':caption' => $caption,
                    ':display_order' => $display_order,
                    ':is_active' => $is_active,
                    ':uploaded_by' => $_SESSION['user_id']
                ]);
                setFlashMessage('success', 'L\'image a été ajoutée avec succès.');
            } else { // edit
                $sql = "UPDATE slider_images SET title = :title, caption = :caption, display_order = :display_order, is_active = :is_active";
                $params = [
                    ':title' => $title,
                    ':caption' => $caption,
                    ':display_order' => $display_order,
                    ':is_active' => $is_active,
                    ':id' => $image_id
                ];
                if (!empty($image_path)) {
                    $sql .= ", image_path = :image_path";
                    $params[':image_path'] = $image_path;
                    // Optionally, delete old image file here
                }
                $sql .= " WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                setFlashMessage('success', 'L\'image a été mise à jour avec succès.');
            }
        } elseif ($action === 'delete') {
            $image_id = (int)($_POST['id'] ?? 0);
            // Optionally, delete image file from server
            $stmt = $conn->prepare("DELETE FROM slider_images WHERE id = ?");
            $stmt->execute([$image_id]);
            setFlashMessage('success', 'L\'image a été supprimée avec succès.');
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }

    header('Location: manage-slider.php');
    exit();
}

// Fetch all slider images
$stmt = $conn->query("SELECT * FROM slider_images ORDER BY display_order ASC, created_at DESC");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/manage-slider.html';

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$template = file_get_contents($templatePath);

// Build image list HTML
$imagesHtml = '';
if (empty($images)) {
    $imagesHtml = '<tr><td colspan="5" class="text-center">Aucune image dans le carrousel pour le moment.</td></tr>';
} else {
    foreach ($images as $image) {
        $status = $image['is_active'] ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Inactif</span>';
        $imagesHtml .= '
            <tr>
                <td><img src="' . htmlspecialchars($image['image_path']) . '" alt="" class="img-thumbnail" width="100"></td>
                <td>' . htmlspecialchars($image['title']) . '</td>
                <td>' . $image['display_order'] . '</td>
                <td>' . $status . '</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-info btn-edit-slider" 
                            data-id="' . $image['id'] . '" 
                            data-title="' . htmlspecialchars($image['title']) . '"
                            data-caption="' . htmlspecialchars($image['caption']) . '"
                            data-order="' . $image['display_order'] . '"
                            data-active="' . $image['is_active'] . '">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form action="manage-slider.php" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="' . $image['id'] . '">
                        <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                        <button type="submit" class="btn btn-sm btn-danger btn-delete-slider">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        ';
    }
}

$contentHtml = str_replace('<tbody>', '<tbody>' . $imagesHtml, $template);
$contentHtml = str_replace('{{csrf_token}}', generateCsrfToken(), $contentHtml);


$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_json}}' => $flash_json,
    '{{title}}' => 'AEESGS - Gérer le carrousel',
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
