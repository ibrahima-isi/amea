<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\UserController;
use Amea\Controller\ProfileController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class UserControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /** @dataProvider userActions */
    public function testUserActionsAreDispatchable(string $action): void
    {
        $m = new ReflectionMethod(UserController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function userActions(): array
    {
        return [['index'], ['create'], ['store'], ['edit'], ['update'], ['delete']];
    }

    /** @dataProvider profileActions */
    public function testProfileActionsAreDispatchable(string $action): void
    {
        $m = new ReflectionMethod(ProfileController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function profileActions(): array
    {
        return [['show'], ['update']];
    }
}
