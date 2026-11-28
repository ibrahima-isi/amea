<?php

declare(strict_types=1);

namespace Amea\Service;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Brevo SMTP transport that keeps a single STARTTLS/AUTH session alive across
 * a batch of messages to avoid per-recipient handshake latency.
 */
final class SmtpMailBatchTransport implements MailBatchTransportInterface
{
    private ?PHPMailer $mail = null;

    public function __construct(
        private string $smtpUser,
        private string $smtpPass,
        private string $host = 'smtp-relay.brevo.com',
        private int $port = 587,
        private int $timeout = 15
    ) {
    }

    public function open(): void
    {
        if ($this->mail !== null) {
            return;
        }
        $this->mail = $this->createMailer();
    }

    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        ?string $replyTo = null
    ): array {
        if ($this->mail === null) {
            $this->open();
        }
        $mail = $this->mail;
        if ($mail === null) {
            return ['ok' => false, 'error' => 'Transport SMTP indisponible.'];
        }

        try {
            $mail->clearAllRecipients();
            $mail->clearReplyTos();
            $mail->clearAttachments();
            $mail->clearCustomHeaders();

            $mail->setFrom($fromAddress, $fromName);
            if ($replyTo !== null && $replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = trim((string) preg_replace('/\s+/', ' ', strip_tags($htmlBody)));

            $mail->send();
            return ['ok' => true, 'error' => null];
        } catch (MailException $e) {
            $error = $e->getMessage();
            error_log('[SmtpMailBatchTransport] Send failed to ' . $to . ': ' . $error);
            // Reset broken SMTP sockets so the next recipient in the chunk can reconnect cleanly.
            $this->resetBrokenConnection();
            return ['ok' => false, 'error' => $error];
        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log('[SmtpMailBatchTransport] Unexpected failure to ' . $to . ': ' . $error);
            $this->resetBrokenConnection();
            return ['ok' => false, 'error' => $error];
        }
    }

    public function close(): void
    {
        if ($this->mail === null) {
            return;
        }
        try {
            $this->mail->smtpClose();
        } catch (Throwable) {
            // Best-effort close on teardown.
        }
        $this->mail = null;
    }

    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtpUser;
        $mail->Password = $this->smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $this->port;
        $mail->Timeout = $this->timeout;
        $mail->SMTPKeepAlive = true;
        $mail->CharSet = 'UTF-8';
        return $mail;
    }

    private function resetBrokenConnection(): void
    {
        try {
            $this->mail?->smtpClose();
        } catch (Throwable) {
            // Ignore secondary close error.
        }
        $this->mail = null;
    }
}
