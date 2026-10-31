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

    public function testStaffUpdateNotificationRendersTheExistingProducerFields(): void
    {
        $view = new View(dirname(__DIR__, 3));
        $body = $view->render('emails/admin-update-notification.html', [
            'id' => 42, 'first_name' => 'Aminata', 'last_name' => 'Fixture',
            'email' => 'member@example.invalid', 'phone' => '771234567',
            'status' => 'ETUDIANT', 'institution' => 'Université Fixture',
            'study_field' => 'Biologie', 'study_level' => 'Licence',
            'residence' => 'Dakar', 'housing_type' => 'Famille',
            'update_date' => '31/10/2026 à 12:30',
        ]);

        foreach (['#42', 'Bonjour Aminata Fixture,', 'member@example.invalid', '771234567',
            'ETUDIANT', 'Université Fixture', 'Biologie', 'Licence', 'Dakar', 'Famille',
            '31/10/2026 à 12:30'] as $value) {
            $this->assertStringContainsString($value, $body);
        }
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
