<?php
namespace Amea\Model;

class User
{
    public function __construct(
        private int     $id,
        private string  $username,
        private string  $email,
        private string  $lastName,
        private string  $firstName,
        private string  $role,
        private bool    $isActive,
        private ?string $permissionsJson,
        private string  $createdAt,
        private ?string $lastLogin = null,
        private ?string $password = null,
        private int     $sessionVersion = 1
    ) {}

    public function getId(): int               { return $this->id; }
    public function getUsername(): string      { return $this->username; }
    public function getEmail(): string         { return $this->email; }
    public function getLastName(): string      { return $this->lastName; }
    public function getFirstName(): string     { return $this->firstName; }
    public function getFullName(): string      { return $this->firstName . ' ' . $this->lastName; }
    public function getRole(): string          { return $this->role; }
    public function isActive(): bool           { return $this->isActive; }
    public function isSuperAdmin(): bool       { return $this->id === 1; }
    public function getPassword(): ?string     { return $this->password; }
    public function getCreatedAt(): string     { return $this->createdAt; }
    public function getLastLogin(): ?string    { return $this->lastLogin; }
    public function getSessionVersion(): int   { return $this->sessionVersion; }

    public function getPermissions(): array
    {
        if (empty($this->permissionsJson)) {
            return [];
        }
        $decoded = json_decode($this->permissionsJson, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function hasPermission(string $module): bool
    {
        if ($this->id === 1) return true;
        return in_array($module, $this->getPermissions(), true);
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id:                 (int)($row['id'] ?? 0),
            username:           $row['username'] ?? '',
            email:              $row['email'] ?? '',
            lastName:           $row['last_name'] ?? '',
            firstName:          $row['first_name'] ?? '',
            role:               $row['role'] ?? 'user',
            isActive:           (bool)($row['is_active'] ?? false),
            permissionsJson:    $row['permissions'] ?? null,
            createdAt:          $row['created_at'] ?? '',
            lastLogin:          $row['last_login'] ?? null,
            password:           $row['password'] ?? null,
            sessionVersion:     (int)($row['session_version'] ?? 1)
        );
    }
}
