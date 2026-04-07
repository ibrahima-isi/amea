<?php
/**
 * Email service using PHPMailer + Brevo SMTP.
 * File: functions/email-service.php
 *
 * Usage: sendMail($to, $subject, $body)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendMail(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USER');
        $mail->Password   = env('MAIL_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('no-reply@aeesgs.org', 'AEESGS');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        logError('Email sending failed to ' . $to, $e);
        return false;
    }
}

/**
 * Load an email template and replace {{placeholders}} with $data values.
 */
function renderEmailTemplate(string $templatePath, array $data): string
{
    $tpl = file_get_contents($templatePath);
    if ($tpl === false) {
        return '';
    }
    $pairs = [];
    foreach ($data as $key => $value) {
        $pairs['{{' . $key . '}}'] = $value;
    }
    return strtr($tpl, $pairs);
}
