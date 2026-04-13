<?php
namespace Amea\Repository;

use Amea\Model\User;

class UserRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id_user = ? LIMIT 1");
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
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND est_actif = 1 LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ? User::fromRow($row) : null;
    }

    /** @return User[] */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY date_creation DESC");
        return array_map(fn($row) => User::fromRow($row), $stmt->fetchAll());
    }

    public function save(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (username, email, nom, prenom, password, role, permissions, est_actif, date_creation)
             VALUES (:username, :email, :nom, :prenom, :password, :role, :permissions, :est_actif, NOW())"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data['id_user'] = $id;
        $stmt = $this->pdo->prepare(
            "UPDATE users SET username = :username, email = :email, role = :role,
             permissions = :permissions, est_actif = :est_actif WHERE id_user = :id_user"
        );
        return $stmt->execute($data);
    }

    public function updatePassword(int $id, string $hashedPassword): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
        return $stmt->execute([$hashedPassword, $id]);
    }

    public function updateLastLogin(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE users SET derniere_connexion = NOW() WHERE id_user = ?");
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id_user = ?");
        return $stmt->execute([$id]);
    }

    public function existsByUsername(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE username = ? AND id_user != ? LIMIT 1");
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
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE email = ? AND id_user != ? LIMIT 1");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
        }
        return (bool)$stmt->fetchColumn();
    }
}
