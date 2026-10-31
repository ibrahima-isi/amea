<?php

namespace Amea\Tests\Integration\View;

use Amea\Core\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailTemplatesTest extends TestCase
{
    #[DataProvider('recipientTemplates')]
    public function testFrenchGreetingRetainsTheFullRecipientName(string $template): void
    {
        $view = new View(dirname(__DIR__, 3));
        $body = $view->render('emails/' . $template, [
            'first_name' => 'Aminata <safe>',
            'last_name' => 'Diallo & Co',
        ]);

        $this->assertStringContainsString('Bonjour Aminata &lt;safe&gt; Diallo &amp; Co,', $body);
    }

    public static function recipientTemplates(): array
    {
        return array_map(static fn(string $template) => [$template], [
            'admin-update-notification.html',
            'bulk-communication.html',
            'cgu-reminder.html',
            'grade-upgrade-email.html',
            'password-reset-email.html',
            'password-updated-confirmation.html',
            'registration-confirmation.html',
            'registration-finalized.html',
        ]);
    }
}
