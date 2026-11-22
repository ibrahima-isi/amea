<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\DashboardController;
use Amea\Core\View;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testIndexMethodExists(): void
    {
        $this->assertTrue(method_exists(DashboardController::class, 'index'));
    }

    public function testIndexIsDispatchableWithNoArguments(): void
    {
        $method = new ReflectionMethod(DashboardController::class, 'index');
        $this->assertSame(0, $method->getNumberOfRequiredParameters());
    }

    public function testDashboardRendersEmptyChartsWithoutPercentages(): void
    {
        $view = new View(dirname(__DIR__, 3));
        $html = $view->render('admin/pages/dashboard.html.twig', [
            'stats' => ['total' => 0, 'men' => null, 'women' => null, 'students' => null],
            'recent_week' => 0,
            'recent_month' => 0,
            'school_labels' => [],
            'school_values' => [],
        ]);

        $this->assertStringContainsString('Aucun établissement à afficher.', $html);
        $this->assertStringContainsString('Aucun statut à afficher.', $html);
        $this->assertStringContainsString('Aucune répartition à afficher.', $html);
        $this->assertStringNotContainsString('data-dashboard-chart=', $html);
    }

    public function testDashboardPreservesSchoolLabelsInTextAndChartData(): void
    {
        $label = 'Institut « Sciences & Métiers » de l’Ouest';
        $view = new View(dirname(__DIR__, 3));
        $html = $view->render('admin/pages/dashboard.html.twig', [
            'stats' => ['total' => 3, 'students' => 3],
            'recent_week' => 2,
            'recent_month' => 3,
            'school_labels' => [$label],
            'school_values' => [3],
        ]);

        $this->assertStringContainsString('Institut « Sciences &amp; Métiers » de l’Ouest · 3', $html);
        $this->assertSame(1, preg_match('/data-labels="([^"]+)" data-values="\[3\]"/', $html, $matches));
        $this->assertSame([$label], json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true));
    }
}
