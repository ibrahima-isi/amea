<?php
/**
 * Legal Notice and Data Privacy Policy page.
 * File: legal-notice.php
 */

require_once 'functions/utility-functions.php';
require_once 'config/session.php';

// Prepare header/footer
$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());

$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
]);

// Determine if we are in a modal context or full page (optional, but good for potential reuse)
$isModal = isset($_GET['modal']) && $_GET['modal'] === 'true';

$templatePath = __DIR__ . '/templates/legal-notice.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

$tpl = file_get_contents($templatePath);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
]);

echo addVersionToAssets($output);
