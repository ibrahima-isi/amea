<?php

require_once 'config/session.php';
require_once 'functions/utility-functions.php';

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
$flash_script = '';
if ($flash) {
    $flash_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '{$flash['type']}',
                    title: 'Succès',
                    text: '{$flash['message']}',
                });
            });
        </script>
    ";
}

$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => 'active',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
    '{{flash_script}}' => $flash_script,
]);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
]);

echo $output;

