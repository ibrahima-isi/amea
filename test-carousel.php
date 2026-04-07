<?php
require_once __DIR__ . '/config/database.php';

$stmt = $conn->query("SELECT * FROM slider_images ORDER BY display_order ASC");
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<pre>';
echo "Nombre d'images en DB: " . count($images) . "\n\n";

foreach ($images as $img) {
    $path   = $img['image_path'];
    $full   = __DIR__ . '/' . $path;
    $exists = file_exists($full);
    $perms  = $exists ? substr(sprintf('%o', fileperms($full)), -4) : 'N/A';

    echo "ID: {$img['id']} | Actif: {$img['is_active']}\n";
    echo "  DB path   : $path\n";
    echo "  Full path : $full\n";
    echo "  Exists    : " . ($exists ? "YES" : "NO — fichier manquant") . "\n";
    echo "  Perms     : $perms\n";
    echo "\n";
}

// Check uploads/slider/ directory
$dir = __DIR__ . '/uploads/slider/';
echo "uploads/slider/ exists : " . (is_dir($dir) ? "YES" : "NO") . "\n";
if (is_dir($dir)) {
    $files = scandir($dir);
    $files = array_diff($files, ['.', '..']);
    echo "Files in uploads/slider/ : " . count($files) . "\n";
    foreach ($files as $f) echo "  - $f\n";
}

// Check for any .htaccess in uploads/
$htaccess = __DIR__ . '/uploads/.htaccess';
echo "\nuploads/.htaccess exists : " . (file_exists($htaccess) ? "YES — " . file_get_contents($htaccess) : "NO") . "\n";

echo '</pre>';
