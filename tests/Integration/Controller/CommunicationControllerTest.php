<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\CommunicationController;
use Amea\Core\View;
use Amea\Model\CommunicationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CommunicationControllerTest extends TestCase
{
    #[DataProvider('actions')]
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $m = new ReflectionMethod(CommunicationController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [['index'], ['count'], ['send'], ['dispatch']];
    }

    public function testCommunicationsTemplateRendersStatsFiltersAndDispatchPanel(): void
    {
        $view = new View(dirname(__DIR__, 3));
        $twig = $view->getTwig();
        $twig->addGlobal('public_logo_available', false);
        $twig->addGlobal('public_home_url', '/');
        $twig->addGlobal('public_register_url', 'register.php');
        $twig->addGlobal('staff_navigation', ['communications' => true]);
        $twig->addGlobal('staff_route', 'communications.php');
        $twig->addGlobal('session', ['first_name' => 'Admin', 'last_name' => 'AEESGS']);
        $twig->addGlobal('flash', null);
        $twig->addGlobal('csrf_token', 'test-csrf-token');
        $twig->addGlobal('settings', ['contact_email' => 'contact@aeesgs.org', 'contact_phone' => '', 'association_name' => 'AEESGS', 'year' => '2026']);

        $html = $view->render('admin/pages/communications.html.twig', [
            'institutions' => ['UCAD', 'UGB'],
            'stats' => [
                'active_total' => 120,
                'reachable_with_email' => 112,
                'missing_email' => 8,
                'daily_sent' => 45,
                'daily_limit' => 300,
            ],
            'default_recipient_count' => 110,
            'reply_to_email' => 'contact@aeesgs.org',
            'active_communication' => [
                'id' => 7,
                'subject' => 'Rentrée 2026',
                'status' => 'QUEUED',
                'total_recipients' => 110,
                'sent_count' => 0,
                'failed_count' => 0,
                'cancelled_count' => 0,
                'pending_count' => 110,
                'sending_count' => 0,
            ],
            'staff_email' => 'admin@aeesgs.org',
            'history' => [
                [
                    'id' => 7,
                    'subject' => 'Rentrée 2026',
                    'status' => 'COMPLETED_WITH_ERRORS',
                    'total_recipients' => 110,
                    'sent_count' => 108,
                    'failed_count' => 2,
                    'cancelled_count' => 0,
                    'pending_count' => 0,
                    'sending_count' => 0,
                    'author_name' => 'Admin AEESGS',
                    'created_at' => '2026-11-27 10:00:00',
                    'completed_at' => '2026-11-27 10:01:00',
                    'filters' => ['status' => 'ETUDIANT', 'institution' => 'UCAD', 'consent' => '', 'kyc_status' => 'exclude_rejected'],
                    'failures' => [
                        ['email' => 'bounce@example.invalid', 'first_name' => 'Oumar', 'last_name' => 'Diallo', 'attempts' => 5, 'last_error' => 'SMTP 550 mailbox unavailable'],
                    ],
                ],
            ],
            'history_page' => 1,
            'history_total_pages' => 1,
            'status_labels' => CommunicationStatus::labelsMap(),
            'status_badges' => CommunicationStatus::badgesMap(),
        ]);

        self::assertStringContainsString('Adhérents actifs', $html);
        self::assertStringContainsString('Joignables par email', $html);
        self::assertStringContainsString('45 / 300', $html);
        self::assertStringContainsString('Diplômé(e)', $html);
        self::assertStringContainsString('Tous sauf rejetés', $html);
        self::assertStringContainsString('liveDispatchPanel', $html);
        self::assertStringContainsString('data-communication-id="7"', $html);
        self::assertStringContainsString('data-auto-start="1"', $html);
        self::assertStringContainsString('data-status-labels=', $html);
        self::assertStringContainsString('Envoyer un test à moi-même', $html);
        self::assertStringContainsString('Historique des communications', $html);
        self::assertStringContainsString('Réessayer les échecs', $html);
        self::assertStringContainsString('SMTP 550 mailbox unavailable', $html);
    }
}
