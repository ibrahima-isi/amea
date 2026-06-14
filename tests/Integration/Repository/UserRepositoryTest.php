<?php

namespace Amea\Tests\Integration\Repository;

use Amea\Core\Container;
use Amea\Model\User;
use Amea\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    private \PDO $db;
    private UserRepository $repo;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->db = makeTestDb();
        Container::setDb($this->db);
        $this->repo = new UserRepository($this->db);
    }

    protected function tearDown(): void
    {
        Container::reset();
    }

    private function makeUser(array $overrides = []): array
    {
        return array_merge([
            'username'    => 'alice',
            'email'       => 'alice@test.sn',
            'last_name'   => 'Traoré',
            'first_name'  => 'Alice',
            'password'    => password_hash('secret', PASSWORD_DEFAULT),
            'role'        => 'admin',
            'permissions' => json_encode(['students']),
            'is_active'   => 1,
        ], $overrides);
    }

    public function testSaveReturnsValidId(): void
    {
        $id = $this->repo->save($this->makeUser());
        // id=1 is the _superadmin seed inserted by makeTestDb()
        $this->assertGreaterThan(1, $id);
    }

    public function testFindById(): void
    {
        $id = $this->repo->save($this->makeUser());
        $user = $this->repo->findById($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('alice', $user->getUsername());
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(9999));
    }

    public function testFindByUsername(): void
    {
        $this->repo->save($this->makeUser());
        $this->assertSame('alice', $this->repo->findByUsername('alice')?->getUsername());
        $this->assertNull($this->repo->findByUsername('nobody'));
    }

    public function testFindActiveByUsernameExcludesInactive(): void
    {
        $this->repo->save($this->makeUser([
            'username'  => 'inactive',
            'email'     => 'i@test.sn',
            'is_active' => 0,
        ]));
        $this->assertNull($this->repo->findActiveByUsername('inactive'));
    }

    public function testFindActiveByUsernameReturnsActiveUser(): void
    {
        $this->repo->save($this->makeUser());
        $user = $this->repo->findActiveByUsername('alice');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('alice', $user->getUsername());
    }

    public function testFindAll(): void
    {
        $this->repo->save($this->makeUser());
        $this->repo->save($this->makeUser(['username' => 'bob', 'email' => 'bob@test.sn']));
        // +1 for the _superadmin seed
        $this->assertCount(3, $this->repo->findAll());
    }

    public function testUpdate(): void
    {
        $id = $this->repo->save($this->makeUser());

        // update() requires ALL of these fields — partial updates are not supported
        $this->repo->update($id, [
            'username'    => 'alice',
            'email'       => 'alice@test.sn',
            'last_name'   => 'UpdatedLast',
            'first_name'  => 'Alice',
            'role'        => 'admin',
            'permissions' => json_encode(['students']),
            'is_active'   => 1,
        ]);

        $updated = $this->repo->findById($id);
        $this->assertSame('UpdatedLast', $updated->getLastName());
    }

    public function testExistsByUsername(): void
    {
        $id = $this->repo->save($this->makeUser());
        $this->assertTrue($this->repo->existsByUsername('alice'));
        // excluding same id should return false (only alice exists)
        $this->assertFalse($this->repo->existsByUsername('alice', $id));
    }

    public function testExistsByUsernameReturnsFalseForUnknown(): void
    {
        $this->assertFalse($this->repo->existsByUsername('ghost'));
    }

    public function testExistsByEmail(): void
    {
        $id = $this->repo->save($this->makeUser());
        $this->assertTrue($this->repo->existsByEmail('alice@test.sn'));
        $this->assertFalse($this->repo->existsByEmail('alice@test.sn', $id));
    }

    public function testDelete(): void
    {
        $id = $this->repo->save($this->makeUser());
        $this->repo->delete($id);
        $this->assertNull($this->repo->findById($id));
    }

    public function testIncrementSessionVersion(): void
    {
        $id = $this->repo->save($this->makeUser());
        // Default session_version is 1; after one increment it becomes 2
        $this->repo->incrementSessionVersion($id);
        $this->assertSame(2, $this->repo->findById($id)->getSessionVersion());
    }

    public function testUpdatePassword(): void
    {
        $id = $this->repo->save($this->makeUser());
        $newHash = password_hash('newpass', PASSWORD_DEFAULT);
        $this->repo->updatePassword($id, $newHash);
        $user = $this->repo->findById($id);
        $this->assertTrue(password_verify('newpass', $user->getPassword()));
    }

    public function testFindByEmail(): void
    {
        $this->repo->save($this->makeUser());
        $user = $this->repo->findByEmail('alice@test.sn');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('alice@test.sn', $user->getEmail());
        $this->assertNull($this->repo->findByEmail('nobody@test.sn'));
    }

    public function testIsSessionVersionCurrentReturnsTrueForCurrentVersion(): void
    {
        $id = $this->repo->save($this->makeUser());
        $this->repo->incrementSessionVersion($id); // version is now 2
        $this->assertTrue($this->repo->isSessionVersionCurrent($id, 2));
    }

    public function testIsSessionVersionCurrentReturnsFalseForStaleVersion(): void
    {
        $id = $this->repo->save($this->makeUser());
        $this->repo->incrementSessionVersion($id); // version is now 2
        $this->assertFalse($this->repo->isSessionVersionCurrent($id, 1));
    }

    public function testIsSessionVersionCurrentReturnsFalseForZero(): void
    {
        $id = $this->repo->save($this->makeUser());
        $this->assertFalse($this->repo->isSessionVersionCurrent($id, 0));
    }
}
