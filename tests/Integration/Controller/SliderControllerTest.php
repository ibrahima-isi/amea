<?php

namespace Amea\Tests\Integration\Controller;

use Amea\Controller\SliderController;
use Amea\Controller\UpgradeController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SliderControllerTest extends TestCase
{
    /** @dataProvider sliderActions */
    public function testSliderActionsAreDispatchable(string $action): void
    {
        $m = new ReflectionMethod(SliderController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function sliderActions(): array
    {
        return [['index'], ['handlePost'], ['store'], ['delete']];
    }

    /** @dataProvider upgradeActions */
    public function testUpgradeActionsAreDispatchable(string $action): void
    {
        $m = new ReflectionMethod(UpgradeController::class, $action);
        $this->assertSame(0, $m->getNumberOfRequiredParameters());
    }

    public static function upgradeActions(): array
    {
        return [['index'], ['confirm']];
    }
}
