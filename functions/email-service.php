<?php
/**
 * functions/email-service.php — backward-compat wrappers
 */

require_once __DIR__ . '/../vendor/autoload.php';

function sendMail(string $to, string $subject, string $body): bool
{
    $svc = new \Amea\Service\EmailService(
        $_ENV['MAIL_USER'] ?? '',
        $_ENV['MAIL_PASS'] ?? '',
        'no-reply@aeesgs.org',
        'AEESGS',
        new \Amea\Core\TemplateEngine(__DIR__ . '/..')
    );
    return $svc->send($to, $subject, $body);
}

function renderEmailTemplate(string $templatePath, array $data): string
{
    return (new \Amea\Core\TemplateEngine(__DIR__ . '/..'))->render($templatePath, $data);
}
