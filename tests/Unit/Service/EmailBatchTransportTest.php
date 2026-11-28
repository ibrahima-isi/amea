<?php

declare(strict_types=1);

namespace Amea\Tests\Unit\Service;

use Amea\Service\EmailService;
use Amea\Service\MailBatchTransportInterface;
use PHPUnit\Framework\TestCase;

final class EmailBatchTransportTest extends TestCase
{
    public function testSendBatchReusesOneSessionAndReportsPerRecipientOutcome(): void
    {
        $transport = new class implements MailBatchTransportInterface {
            public int $opens = 0;
            public int $closes = 0;
            /** @var list<array<string, mixed>> */
            public array $sent = [];

            public function open(): void
            {
                $this->opens++;
            }

            public function send(
                string $to,
                string $subject,
                string $htmlBody,
                string $fromAddress,
                string $fromName,
                ?string $replyTo = null
            ): array {
                $this->sent[] = [
                    'to' => $to,
                    'subject' => $subject,
                    'body' => $htmlBody,
                    'from' => $fromAddress,
                    'from_name' => $fromName,
                    'reply_to' => $replyTo,
                ];
                if ($to === 'bounce@example.invalid') {
                    return ['ok' => false, 'error' => 'SMTP 550 mailbox unavailable'];
                }
                return ['ok' => true, 'error' => null];
            }

            public function close(): void
            {
                $this->closes++;
            }
        };

        $service = new EmailService(
            'smtp-user',
            'smtp-pass',
            'noreply@aeesgs.org',
            'AEESGS',
            null,
            null,
            $transport
        );

        $results = $service->sendBatch([
            ['id' => 10, 'to' => 'first@example.invalid', 'subject' => 'Annonce', 'body' => '<p>Bonjour 1</p>', 'replyTo' => 'contact@aeesgs.org'],
            ['id' => 11, 'to' => 'bounce@example.invalid', 'subject' => 'Annonce', 'body' => '<p>Bonjour 2</p>', 'replyTo' => 'contact@aeesgs.org'],
            ['id' => 12, 'to' => 'third@example.invalid', 'subject' => 'Annonce', 'body' => '<p>Bonjour 3</p>', 'replyTo' => 'contact@aeesgs.org'],
        ], 0);

        self::assertSame(1, $transport->opens, 'Batch delivery must open a single SMTP session');
        self::assertSame(1, $transport->closes, 'Batch delivery must close the SMTP session once');
        self::assertCount(3, $transport->sent);
        self::assertSame('noreply@aeesgs.org', $transport->sent[0]['from']);
        self::assertSame('contact@aeesgs.org', $transport->sent[0]['reply_to']);
        self::assertTrue($results[10]['ok']);
        self::assertFalse($results[11]['ok']);
        self::assertSame('SMTP 550 mailbox unavailable', $results[11]['error']);
        self::assertTrue($results[12]['ok']);
    }

    public function testSendBatchClosesSessionEvenWhenTransportThrows(): void
    {
        $transport = new class implements MailBatchTransportInterface {
            public int $opens = 0;
            public int $closes = 0;

            public function open(): void
            {
                $this->opens++;
            }

            public function send(
                string $to,
                string $subject,
                string $htmlBody,
                string $fromAddress,
                string $fromName,
                ?string $replyTo = null
            ): array {
                throw new \RuntimeException('Connection reset by peer');
            }

            public function close(): void
            {
                $this->closes++;
            }
        };

        $service = new EmailService('u', 'p', 'noreply@aeesgs.org', 'AEESGS', null, null, $transport);
        $results = $service->sendBatch([
            ['id' => 1, 'to' => 'a@example.invalid', 'subject' => 'Test', 'body' => '<p>Hi</p>', 'replyTo' => null],
        ]);

        self::assertSame(1, $transport->opens);
        self::assertSame(1, $transport->closes);
        self::assertFalse($results[1]['ok']);
        self::assertStringContainsString('Connection reset by peer', (string) $results[1]['error']);
    }

    public function testSendBatchSuppressesDeliveryInTestEnvWithoutInjectedTransport(): void
    {
        $prev = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'test';
        try {
            $service = new EmailService('bad-user', 'bad-pass', 'noreply@aeesgs.org', 'AEESGS');
            $results = $service->sendBatch([
                ['id' => 5, 'to' => 'invalid-address', 'subject' => 'Synthetic', 'body' => '<p>Body</p>', 'replyTo' => null],
            ]);
            self::assertTrue($results[5]['ok']);
            self::assertNull($results[5]['error']);
        } finally {
            if ($prev === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $prev;
            }
        }
    }
}
