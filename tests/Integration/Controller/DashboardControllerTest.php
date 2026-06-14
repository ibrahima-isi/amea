<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\DashboardController;
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
}
