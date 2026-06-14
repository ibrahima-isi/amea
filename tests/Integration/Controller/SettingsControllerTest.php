<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\SettingsController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SettingsControllerTest extends TestCase
{
    /** @dataProvider actions */
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $m = new ReflectionMethod(SettingsController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [['index'], ['update']];
    }
}
