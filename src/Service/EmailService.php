<?php
namespace Amea\Service;

use Amea\Core\TemplateEngine;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class EmailService
{
    public function __construct(
        private string         $smtpUser,
        private string         $smtpPass,
        private string         $fromAddress,
        private string         $fromName,
        private TemplateEngine $tpl
    ) {}

    public function send(string $to, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUser;
            $mail->Password   = $this->smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log('[EmailService] Send failed to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    public function sendFromTemplate(
        string $to,
        string $subject,
        string $templatePath,
        array  $data
    ): bool {
        $body = $this->tpl->render($templatePath, $data);
        return $this->send($to, $subject, $body);
    }
}
