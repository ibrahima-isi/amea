<?php

namespace Amea\Tests\Unit\Core;

use Amea\Core\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session();
    }

    public function testSetAndGetReturnsValue(): void
    {
        $this->session->set('foo', 'bar');
        $this->assertSame('bar', $this->session->get('foo'));
    }

    public function testGetMissingKeyReturnsDefault(): void
    {
        $this->assertNull($this->session->get('nonexistent'));
        $this->assertSame('default', $this->session->get('nonexistent', 'default'));
        $this->assertSame(42, $this->session->get('nonexistent', 42));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->session->set('key', 'value');
        $this->assertTrue($this->session->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->session->has('does_not_exist'));
    }

    public function testRemoveDeletesKey(): void
    {
        $this->session->set('temp', 'data');
        $this->assertTrue($this->session->has('temp'));

        $this->session->remove('temp');
        $this->assertFalse($this->session->has('temp'));
        $this->assertNull($this->session->get('temp'));
    }

    public function testRemoveNonExistentKeyDoesNotThrow(): void
    {
        // Should not throw even if key doesn't exist
        $this->session->remove('no_such_key');
        $this->assertFalse($this->session->has('no_such_key'));
    }

    public function testUserIdReturnsZeroWhenNoUserInSession(): void
    {
        $this->assertSame(0, $this->session->userId());
    }

    public function testUserIdReturnsCorrectIntAfterSet(): void
    {
        $this->session->set('user_id', 7);
        $this->assertSame(7, $this->session->userId());
    }

    public function testIsLoggedInReturnsFalseWhenNoUserId(): void
    {
        $this->assertFalse($this->session->isLoggedIn());
    }

    public function testIsLoggedInReturnsTrueAfterSettingUserId(): void
    {
        $this->session->set('user_id', 42);
        $this->assertTrue($this->session->isLoggedIn());
    }

    public function testRoleReturnsEmptyStringWhenNotSet(): void
    {
        $this->assertSame('', $this->session->role());
    }

    public function testRoleReturnsCorrectValueAfterSet(): void
    {
        $this->session->set('role', 'admin');
        $this->assertSame('admin', $this->session->role());
    }

    public function testRoleUserValue(): void
    {
        $this->session->set('role', 'user');
        $this->assertSame('user', $this->session->role());
    }

    public function testSetSupportsVariousTypes(): void
    {
        $this->session->set('int_val', 123);
        $this->session->set('bool_val', true);
        $this->session->set('array_val', ['a', 'b', 'c']);

        $this->assertSame(123, $this->session->get('int_val'));
        $this->assertTrue($this->session->get('bool_val'));
        $this->assertSame(['a', 'b', 'c'], $this->session->get('array_val'));
    }

    public function testHasReturnsFalseAfterRemove(): void
    {
        $this->session->set('ephemeral', 'data');
        $this->session->remove('ephemeral');
        $this->assertFalse($this->session->has('ephemeral'));
    }

    public function testSessionIsolationBetweenTests(): void
    {
        // Verify $_SESSION = [] in setUp actually clears the session
        $this->assertFalse($this->session->has('user_id'));
        $this->assertFalse($this->session->isLoggedIn());
        $this->assertSame(0, $this->session->userId());
    }
}
