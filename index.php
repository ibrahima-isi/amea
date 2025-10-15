<?php
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
$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => 'active',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
]);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
]);

echo $output;

