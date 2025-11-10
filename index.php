<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Fetch slider images
$stmt = $conn->query("SELECT * FROM slider_images WHERE is_active = 1 ORDER BY display_order ASC");
$slider_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$carousel_indicators = '';
$carousel_items = '';
$is_first = true;
foreach ($slider_images as $i => $image) {
    $active_class = $is_first ? 'active' : '';
    $carousel_indicators .= '<button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="' . $i . '" class="' . $active_class . '" aria-current="' . ($is_first ? 'true' : 'false') . '" aria-label="Slide ' . ($i + 1) . '"></button>';
    $carousel_items .= '
        <div class="carousel-item ' . $active_class . '">
            <img src="' . htmlspecialchars($image['image_path']) . '" class="d-block w-100" alt="' . htmlspecialchars($image['title']) . '">
            <div class="carousel-caption d-none d-md-block">
                <h5>' . htmlspecialchars($image['title']) . '</h5>
                <p>' . htmlspecialchars($image['caption']) . '</p>
            </div>
        </div>';
    $is_first = false;
}

// Page d'accueil: rendu depuis un template HTML
$templatePath = __DIR__ . '/templates/index.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}
$tpl = file_get_contents($templatePath);

// Header/Footer partials
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = file_get_contents(__DIR__ . '/templates/partials/footer.html');

// Rendu du template
$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => 'active',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
]);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{carousel_indicators}}' => $carousel_indicators,
    '{{carousel_items}}' => $carousel_items,
    '{{flash_json}}' => $flash_json,
]);

echo $output;

