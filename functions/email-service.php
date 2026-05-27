<?php
/**
 * functions/email-service.php — backward-compat wrappers
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Amea\Core\View;
use Amea\Service\EmailService;

function sendMail(string $to, string $subject, string $body): bool
{
    $projectRoot = __DIR__ . '/..';
    $svc = new EmailService(
        $_ENV['MAIL_USER'] ?? '',
        $_ENV['MAIL_PASS'] ?? '',
        'no-reply@aeesgs.org',
        'AEESGS',
        new View($projectRoot)
    );
    return $svc->send($to, $subject, $body);
}

function renderEmailTemplate(string $templatePath, array $data): string
{
    $projectRoot = __DIR__ . '/..';
    return (new View($projectRoot))->render($templatePath, $data);
}
