<?php

namespace Amea\Tests\Unit\Core;

use Amea\Core\Flash;
use Amea\Core\Session;
use PHPUnit\Framework\TestCase;

class FlashTest extends TestCase
{
    private Flash $flash;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session     = new Session();
        $this->flash = new Flash($session);
    }

    public function testGetReturnsNullWhenNoFlashSet(): void
    {
        $this->assertNull($this->flash->get());
    }

    public function testSetAndGetReturnsCorrectTypeAndMessage(): void
    {
        $this->flash->set('success', 'Operation successful');
        $result = $this->flash->get();

        $this->assertIsArray($result);
        $this->assertSame('success', $result['type']);
        $this->assertSame('Operation successful', $result['message']);
    }

    public function testGetClearsFlashAfterFirstRead(): void
    {
        $this->flash->set('info', 'Something happened');
        $first  = $this->flash->get();
        $second = $this->flash->get();

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function testAddStoresMessage(): void
    {
        $this->flash->add('success', 'Added successfully');
        $result = $this->flash->get();

        $this->assertIsArray($result);
        $this->assertSame('success', $result['type']);
        $this->assertSame('Added successfully', $result['message']);
    }

    public function testAddClearsAfterRead(): void
    {
        $this->flash->add('error', 'An error occurred');
        $this->flash->get(); // consume
        $this->assertNull($this->flash->get());
    }

    public function testCssClassSuccess(): void
    {
        $this->assertSame('alert-success', $this->flash->cssClass('success'));
    }

    public function testCssClassError(): void
    {
        $this->assertSame('alert-danger', $this->flash->cssClass('error'));
    }

    public function testCssClassWarning(): void
    {
        $this->assertSame('alert-warning', $this->flash->cssClass('warning'));
    }

    public function testCssClassInfo(): void
    {
        $this->assertSame('alert-info', $this->flash->cssClass('info'));
    }

    public function testCssClassUnknownReturnsSecondary(): void
    {
        $this->assertSame('alert-secondary', $this->flash->cssClass('unknown'));
        $this->assertSame('alert-secondary', $this->flash->cssClass(''));
        $this->assertSame('alert-secondary', $this->flash->cssClass('notice'));
    }

    public function testSetOverwritesPreviousFlash(): void
    {
        $this->flash->set('success', 'First message');
        $this->flash->set('error', 'Second message');
        $result = $this->flash->get();

        $this->assertSame('error', $result['type']);
        $this->assertSame('Second message', $result['message']);
    }
}
