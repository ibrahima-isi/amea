<?php
namespace Amea\Service;

use Amea\Core\View;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class EmailService
{
    private string $projectRoot;

    public function __construct(
        private string $smtpUser,
        private string $smtpPass,
        private string $fromAddress,
        private string $fromName,
        private ?View  $view = null,
        ?string $projectRoot = null
    ) {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    public function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
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
            if ($replyTo !== null && $replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }
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

    public function sendAsync(string $to, string $subject, string $body): bool
    {
        return $this->sendAsyncWithReplyTo($to, $subject, $body, null);
    }

    private function sendAsyncWithReplyTo(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        if ($this->shouldSkipDelivery()) {
            return true;
        }
        if (!$this->hasSmtpCredentials()) {
            return $this->handleMissingCredentials('email', $subject);
        }

        // Async delivery is opt-in (MAIL_ASYNC=1) and works as a durable queue:
        // the message is written to storage/mail-queue and delivered later by the
        // cron worker (scripts/process-mail-queue.php). We no longer exec() a
        // per-message worker — on shared hosting PHP_BINARY is often a non-CLI
        // binary (e.g. lsphp) and the backgrounded process dies silently.
        //
        // If the queue cannot be written (e.g. unwritable directory), we fall
        // back to a synchronous send so a broken queue never drops mail. With
        // MAIL_ASYNC unset, delivery stays fully synchronous (the safe default).
        if ($this->asyncDeliveryEnabled()) {
            $queueFile = $this->writeQueueFile($to, $subject, $body, $replyTo);
            if ($queueFile !== null) {
                return true;
            }
            error_log('[EmailService] Mail queue unavailable; falling back to synchronous send.');
        }

        return $this->send($to, $subject, $body, $replyTo);
    }

    private function asyncDeliveryEnabled(): bool
    {
        $flag = strtolower(trim((string)($_ENV['MAIL_ASYNC'] ?? $_SERVER['MAIL_ASYNC'] ?? getenv('MAIL_ASYNC') ?: '')));
        return in_array($flag, ['1', 'true', 'on', 'yes'], true);
    }

    public function sendFromTemplate(
        string $to,
        string $subject,
        string $templatePath,
        array  $data,
        ?string $replyTo = null
    ): bool {
        if ($this->shouldSkipDelivery()) {
            return true;
        }
        if (!$this->hasSmtpCredentials()) {
            return $this->handleMissingCredentials('template email', $subject);
        }

        if ($this->view === null) {
            error_log('[EmailService] Template email requested without a View instance.');
            return false;
        }

        $body = $this->view->render($templatePath, $data);
        return $this->sendAsyncWithReplyTo($to, $subject, $body, $replyTo);
    }

    private function shouldSkipDelivery(): bool
    {
        $env = $this->appEnvironment();
        return in_array($env, ['demo', 'dev', 'development', 'local', 'test', 'testing'], true);
    }

    private function isExplicitProductionEnvironment(): bool
    {
        return in_array($this->appEnvironment(), ['prod', 'production'], true);
    }

    private function appEnvironment(): string
    {
        return strtolower(trim((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
    }

    private function handleMissingCredentials(string $context, string $subject): bool
    {
        if ($this->isExplicitProductionEnvironment()) {
            error_log("[EmailService] Missing SMTP credentials for {$context}: {$subject}");
            return false;
        }

        return true;
    }

    private function hasSmtpCredentials(): bool
    {
        return $this->smtpUser !== ''
            && $this->smtpPass !== ''
            && $this->smtpUser !== 'your_brevo_login@example.com'
            && $this->smtpPass !== 'your_brevo_smtp_key';
    }

    private function writeQueueFile(string $to, string $subject, string $body, ?string $replyTo = null): ?string
    {
        $queueDir = $this->projectRoot . '/storage/mail-queue';
        if (!is_dir($queueDir) && !mkdir($queueDir, 0775, true) && !is_dir($queueDir)) {
            error_log('[EmailService] Failed to create mail queue directory: ' . $queueDir);
            return null;
        }

        $now = time();
        $payload = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'attempts' => 0,
            'created_at' => $now,
            'next_attempt_at' => $now,
        ];
        if ($replyTo !== null && $replyTo !== '') {
            $payload['replyTo'] = $replyTo;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            error_log('[EmailService] Failed to encode queued email payload.');
            return null;
        }

        $queueFile = sprintf(
            '%s/%s_%s.json',
            $queueDir,
            date('Ymd_His'),
            bin2hex(random_bytes(8))
        );

        if (file_put_contents($queueFile, $encoded, LOCK_EX) === false) {
            error_log('[EmailService] Failed to write mail queue file: ' . $queueFile);
            return null;
        }

        return $queueFile;
    }
}
