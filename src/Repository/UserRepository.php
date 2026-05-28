<?php
namespace Amea\Repository;

use Amea\Model\User;

class UserRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    public function findActiveByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    /** @return User[] */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        return array_map(fn($row) => User::fromRow($row), $stmt->fetchAll());
    }

    public function save(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, last_name, first_name, password, role, permissions, is_active, created_at)
             VALUES (:username, :email, :last_name, :first_name, :password, :role, :permissions, :is_active, CURRENT_TIMESTAMP)"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data['id'] = $id;
        $stmt = $this->pdo->prepare(
            "UPDATE users SET username = :username, email = :email, last_name = :last_name, first_name = :first_name, role = :role,
             permissions = :permissions, is_active = :is_active WHERE id = :id"
        );
        return $stmt->execute($data);
    }

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $id]);
    }

    public function incrementSessionVersion(int $id): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET session_version = COALESCE(session_version, 1) + 1 WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $this->sessionVersion($id);
    }

    public function sessionVersion(int $id): int
    {
        $stmt = $this->pdo->prepare("SELECT session_version FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $version = $stmt->fetchColumn();

        return $version === false ? 0 : (int)$version;
    }

    public function isSessionVersionCurrent(int $id, int $sessionVersion): bool
    {
        return $sessionVersion > 0 && $this->sessionVersion($id) === $sessionVersion;
    }

    public function updateLastLogin(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE username = ? AND id != ? LIMIT 1");
            $stmt->execute([$username, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
        }
        return (bool)$stmt->fetchColumn();
    }
}
