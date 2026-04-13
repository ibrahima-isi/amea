<?php
namespace Amea\Model;

class User
{
    public function __construct(
        private int     $id,
        private string  $username,
        private string  $email,
        private string  $nom,
        private string  $prenom,
        private string  $role,
        private bool    $estActif,
        private ?string $permissionsJson,
        private string  $dateCreation,
        private ?string $derniereConnexion = null,
        private ?string $password = null
    ) {}

    public function getId(): int               { return $this->id; }
    public function getUsername(): string      { return $this->username; }
    public function getEmail(): string         { return $this->email; }
    public function getNom(): string           { return $this->nom; }
    public function getPrenom(): string        { return $this->prenom; }
    public function getFullName(): string      { return $this->prenom . ' ' . $this->nom; }
    public function getRole(): string          { return $this->role; }
    public function isActif(): bool            { return $this->estActif; }
    public function isSuperAdmin(): bool       { return $this->id === 1; }
    public function getPassword(): ?string     { return $this->password; }
    public function getDateCreation(): string  { return $this->dateCreation; }
    public function getDerniereConnexion(): ?string { return $this->derniereConnexion; }

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
            id:                 (int)$row['id_user'],
            username:           $row['username'] ?? '',
            email:              $row['email'] ?? '',
            nom:                $row['nom'] ?? '',
            prenom:             $row['prenom'] ?? '',
            role:               $row['role'] ?? 'user',
            estActif:           (bool)($row['est_actif'] ?? false),
            permissionsJson:    $row['permissions'] ?? null,
            dateCreation:       $row['date_creation'] ?? '',
            derniereConnexion:  $row['derniere_connexion'] ?? null,
            password:           $row['password'] ?? null
        );
    }
}
