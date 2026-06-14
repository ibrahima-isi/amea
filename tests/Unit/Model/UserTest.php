<?php

namespace Amea\Tests\Unit\Model;

use Amea\Model\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private array $row;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->row = [
            'id'              => 42,
            'username'        => 'jdoe',
            'email'           => 'j@doe.com',
            'last_name'       => 'Doe',
            'first_name'      => 'John',
            'role'            => 'admin',
            'is_active'       => 1,
            'permissions'     => json_encode(['students', 'export']),
            'created_at'      => '2024-01-01',
            'last_login'      => null,
            'password'        => 'hash',
            'session_version' => 1,
        ];
    }

    public function testGettersReturnCorrectValues(): void
    {
        $u = User::fromRow($this->row);

        $this->assertSame(42, $u->getId());
        $this->assertSame('jdoe', $u->getUsername());
        $this->assertSame('j@doe.com', $u->getEmail());
        $this->assertSame('John', $u->getFirstName());
        $this->assertSame('Doe', $u->getLastName());
        $this->assertSame('John Doe', $u->getFullName());
        $this->assertSame('admin', $u->getRole());
        $this->assertTrue($u->isActive());
        $this->assertSame(1, $u->getSessionVersion());
        $this->assertSame('hash', $u->getPassword());
        $this->assertSame('2024-01-01', $u->getCreatedAt());
        $this->assertNull($u->getLastLogin());
    }

    public function testPermissions(): void
    {
        $u = User::fromRow($this->row);

        $this->assertSame(['students', 'export'], $u->getPermissions());
        $this->assertTrue($u->hasPermission('students'));
        $this->assertTrue($u->hasPermission('export'));
        $this->assertFalse($u->hasPermission('users'));
        $this->assertFalse($u->isSuperAdmin());
    }

    public function testSuperAdminBypassesPermissions(): void
    {
        $super = User::fromRow(array_merge($this->row, ['id' => 1, 'permissions' => null]));

        $this->assertTrue($super->isSuperAdmin());
        $this->assertTrue($super->hasPermission('anything'));
        $this->assertTrue($super->hasPermission('students'));
        $this->assertSame([], $super->getPermissions());
    }

    public function testNullPermissionsReturnEmptyArray(): void
    {
        $u = User::fromRow(array_merge($this->row, ['permissions' => null]));

        $this->assertSame([], $u->getPermissions());
        $this->assertFalse($u->hasPermission('students'));
    }

    public function testMalformedPermissionsReturnEmptyArray(): void
    {
        $u = User::fromRow(array_merge($this->row, ['permissions' => 'not-json']));

        $this->assertSame([], $u->getPermissions());
        $this->assertFalse($u->hasPermission('students'));
    }

    public function testEmptyPermissionsStringReturnEmptyArray(): void
    {
        $u = User::fromRow(array_merge($this->row, ['permissions' => '']));

        $this->assertSame([], $u->getPermissions());
    }

    public function testIsActiveReturnsFalseWhenInactive(): void
    {
        $u = User::fromRow(array_merge($this->row, ['is_active' => 0]));

        $this->assertFalse($u->isActive());
    }

    public function testFromRowUsesDefaults(): void
    {
        $u = User::fromRow([]);

        $this->assertSame(0, $u->getId());
        $this->assertSame('', $u->getUsername());
        $this->assertSame('', $u->getEmail());
        $this->assertSame('user', $u->getRole());
        $this->assertFalse($u->isActive());
        $this->assertSame(1, $u->getSessionVersion());
    }
}
