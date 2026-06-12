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

        // Async delivery is opt-in (MAIL_ASYNC=1). On shared hosting PHP_BINARY
        // is often a non-CLI binary (e.g. lsphp): the exec'd worker dies before
        // sending, but the backgrounded shell still exits 0, so the failure is
        // invisible to us. Synchronous delivery is the only verifiable default.
        if ($this->asyncDeliveryEnabled()) {
            $queueFile = $this->writeQueueFile($to, $subject, $body, $replyTo);
            if ($queueFile !== null && $this->launchWorker($queueFile)) {
                return true;
            }
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

        $payload = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
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

    private function launchWorker(string $queueFile): bool
    {
        $script = $this->projectRoot . '/scripts/send-email.php';
        if (!is_file($script)) {
            return false;
        }
        if (!function_exists('exec')) {
            return false;
        }

        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($queueFile)
            . ' > /dev/null 2>&1 &';

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return false;
        }

        return true;
    }
}
