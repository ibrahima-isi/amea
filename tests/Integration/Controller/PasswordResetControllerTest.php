<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\PasswordResetController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PasswordResetControllerTest extends TestCase
{
    /** @dataProvider actions */
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $m = new ReflectionMethod(PasswordResetController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [['forgotForm'], ['forgotSubmit'], ['resetForm'], ['resetSubmit']];
    }
}
