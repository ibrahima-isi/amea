<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\AcceptTermsController;
use Amea\Controller\DownloadController;
use Amea\Controller\LegalController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DownloadControllerTest extends TestCase
{
    /** @dataProvider actions */
    public function testActionsAreDispatchableWithNoArguments(string $class, string $action): void
    {
        $m = new ReflectionMethod($class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function actions(): array
    {
        return [
            [DownloadController::class,   'serve'],
            [LegalController::class,      'index'],
            [AcceptTermsController::class,  'accept'],
        ];
    }
}
