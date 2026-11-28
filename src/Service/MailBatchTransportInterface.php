<?php

declare(strict_types=1);

namespace Amea\Service;

/**
 * Reusable session seam for sending a batch of outbound emails over one
 * persistent transport connection.
 */
interface MailBatchTransportInterface
{
    public function open(): void;

    /**
     * @return array{ok: bool, error: ?string}
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $fromAddress,
        string $fromName,
        ?string $replyTo = null
    ): array;

    public function close(): void;
}
