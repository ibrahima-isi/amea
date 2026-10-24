<?php
namespace Tests\Unit\Service;

use Amea\Service\EmailService;
use PHPUnit\Framework\TestCase;

final class EmailDeliveryTest extends TestCase
{
    public function testDirectDeliveryIsSuppressedInTestEnvironmentBeforeTransportSetup(): void
    {
        $previous = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'test';
        try {
            // Invalid addresses deliberately fail transport setup if suppression regresses.
            $service = new EmailService('synthetic-user', 'synthetic-password', 'invalid', 'Fixture');
            self::assertTrue($service->send('invalid', 'Synthetic notification', '<p>Fixture</p>'));
        } finally {
            if ($previous === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previous;
            }
        }
    }
}
