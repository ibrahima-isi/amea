<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\StudentController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class StudentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /** @dataProvider actionProvider */
    public function testActionsAreDispatchableWithNoArguments(string $action): void
    {
        $method = new ReflectionMethod(StudentController::class, $action);
        $this->assertSame(0, $method->getNumberOfRequiredParameters());
    }

    public static function actionProvider(): array
    {
        return [['index'], ['show'], ['edit'], ['update'], ['delete']];
    }
}
