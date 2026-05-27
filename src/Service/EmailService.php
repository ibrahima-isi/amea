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

    public function sendAsync(string $to, string $subject, string $body): bool
    {
        if (!$this->isProductionEnvironment()) {
            return true;
        }
        if (!$this->hasSmtpCredentials()) {
            error_log('[EmailService] Missing SMTP credentials for production email: ' . $subject);
            return false;
        }

        $queueFile = $this->writeQueueFile($to, $subject, $body);
        if ($queueFile === null) {
            return false;
        }

        return $this->launchWorker($queueFile);
    }

    public function sendFromTemplate(
        string $to,
        string $subject,
        string $templatePath,
        array  $data
    ): bool {
        if (!$this->isProductionEnvironment()) {
            return true;
        }
        if (!$this->hasSmtpCredentials()) {
            error_log('[EmailService] Missing SMTP credentials for production template email: ' . $subject);
            return false;
        }

        if ($this->view === null) {
            error_log('[EmailService] Template email requested without a View instance.');
            return false;
        }

        $body = $this->view->render($templatePath, $data);
        return $this->sendAsync($to, $subject, $body);
    }

    private function isProductionEnvironment(): bool
    {
        $env = strtolower((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'demo'));
        return in_array($env, ['prod', 'production'], true);
    }

    private function hasSmtpCredentials(): bool
    {
        return $this->smtpUser !== ''
            && $this->smtpPass !== ''
            && $this->smtpUser !== 'your_brevo_login@example.com'
            && $this->smtpPass !== 'your_brevo_smtp_key';
    }

    private function writeQueueFile(string $to, string $subject, string $body): ?string
    {
        $queueDir = $this->projectRoot . '/storage/mail-queue';
        if (!is_dir($queueDir) && !mkdir($queueDir, 0775, true) && !is_dir($queueDir)) {
            error_log('[EmailService] Failed to create mail queue directory: ' . $queueDir);
            return null;
        }

        $payload = json_encode([
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            error_log('[EmailService] Failed to encode queued email payload.');
            return null;
        }

        $queueFile = sprintf(
            '%s/%s_%s.json',
            $queueDir,
            date('Ymd_His'),
            bin2hex(random_bytes(8))
        );

        if (file_put_contents($queueFile, $payload, LOCK_EX) === false) {
            error_log('[EmailService] Failed to write mail queue file: ' . $queueFile);
            return null;
        }

        return $queueFile;
    }

    private function launchWorker(string $queueFile): bool
    {
        $script = $this->projectRoot . '/scripts/send-email.php';
        if (!is_file($script)) {
            error_log('[EmailService] Mail worker script is missing: ' . $script);
            return false;
        }
        if (!function_exists('exec')) {
            error_log('[EmailService] exec() is disabled; async mail worker cannot be launched.');
            return false;
        }

        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($queueFile)
            . ' > /dev/null 2>&1 &';

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            error_log('[EmailService] Failed to launch async mail worker for: ' . $queueFile);
            return false;
        }

        return true;
    }
}
