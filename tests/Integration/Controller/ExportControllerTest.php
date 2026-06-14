<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\ExportController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ExportControllerTest extends TestCase
{
    /** @dataProvider actions */
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $m = new ReflectionMethod(ExportController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [['index'], ['preview'], ['download']];
    }
}
