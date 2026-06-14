<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\CommunicationController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CommunicationControllerTest extends TestCase
{
    /** @dataProvider actions */
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $m = new ReflectionMethod(CommunicationController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [['index'], ['count'], ['send']];
    }
}
