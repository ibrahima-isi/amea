<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\ConsentController;
use Amea\Core\View;
use Amea\Model\CommunicationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ConsentControllerTest extends TestCase
{
    #[DataProvider('actions')]
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $m = new ReflectionMethod(ConsentController::class, $action);
        self::assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [['index'], ['members'], ['handlePost'], ['dispatch']];
    }

    public function testConsentTemplateRendersTabsStatsAndTrashActionOnlyWithStudentsPermission(): void
    {
        $view = new View(dirname(__DIR__, 3));
        $twig = $view->getTwig();
        $twig->addGlobal('public_logo_available', false);
        $twig->addGlobal('public_home_url', '/');
        $twig->addGlobal('public_register_url', 'register.php');
        $twig->addGlobal('staff_navigation', ['consent' => true, 'students' => true]);
        $twig->addGlobal('staff_route', 'consent.php');
        $twig->addGlobal('session', ['first_name' => 'Admin', 'last_name' => 'AEESGS']);
        $twig->addGlobal('flash', null);
        $twig->addGlobal('csrf_token', 'test-csrf');
        $twig->addGlobal('settings', ['contact_email' => 'contact@aeesgs.org', 'contact_phone' => '', 'association_name' => 'AEESGS', 'year' => '2026']);

        $baseData = [
            'stats' => ['total_active' => 20, 'consented' => 12, 'not_consented' => 8, 'reminders_sent' => 4, 'deletion_requests' => 1],
            'quota' => ['daily_sent' => 5, 'daily_limit' => 300],
            'active_tab' => 'cgu',
            'active_communication' => null,
            'pending_members' => [
                ['id' => 1, 'first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@example.invalid', 'registration_date' => '2026-10-01', 'cgu_reminder_sent_at' => null],
            ],
            'pending_total' => 1,
            'pending_page' => 1,
            'pending_total_pages' => 1,
            'pending_query' => '',
            'deletion_requests' => [
                ['id' => 3, 'first_name' => 'Fatou', 'last_name' => 'Camara', 'email' => 'fatou@example.invalid', 'deletion_requested_at' => '2026-11-26 12:00:00'],
            ],
            'public_origin_configured' => true,
            'can_trash_students' => true,
            'status_labels' => CommunicationStatus::labelsMap(),
            'status_badges' => CommunicationStatus::badgesMap(),
        ];

        $htmlCgu = $view->render('admin/pages/consent.html.twig', $baseData);
        self::assertStringContainsString('Consentement &amp; RGPD', $htmlCgu);
        self::assertStringContainsString('Awa Diallo', $htmlCgu);
        self::assertStringContainsString('id="pendingConsentCard"', $htmlCgu);
        self::assertStringContainsString('data-members-url="consent-members.php"', $htmlCgu);

        $htmlWithStudents = $view->render('admin/pages/consent.html.twig', array_merge($baseData, ['active_tab' => 'deletion']));
        self::assertStringContainsString('Déplacer vers la corbeille', $htmlWithStudents);
        self::assertStringContainsString('student-details.php?id=3', $htmlWithStudents);

        $htmlWithoutStudents = $view->render('admin/pages/consent.html.twig', array_merge($baseData, ['active_tab' => 'deletion', 'can_trash_students' => false]));
        self::assertStringNotContainsString('Déplacer vers la corbeille', $htmlWithoutStudents);
        self::assertStringContainsString('student-details.php?id=3', $htmlWithoutStudents);
    }
}
