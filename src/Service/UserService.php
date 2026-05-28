<?php
namespace Amea\Service;

use Amea\Repository\UserRepository;

class UserService
{
    public const MODULES = [
        'students', 'export', 'users', 'slider',
        'upgrade', 'documents', 'communications', 'settings',
    ];

    public function __construct(private UserRepository $repo) {}

    /**
     * Sanitize permissions array against the known module whitelist.
     * @return string[]
     */
    public function sanitizePermissions(array $submitted): array
    {
        return array_values(array_intersect($submitted, self::MODULES));
    }

    /**
     * Build the permissions JSON string to persist, applying:
     *   1. Whitelist filtering
     *   2. Self-edit escalation prevention
     *   3. Super Admin lock (ID 1 always gets all permissions)
     */
    public function buildPermissionsJson(
        string  $role,
        array   $submittedPermissions,
        bool    $isSelf,
        bool    $isSuperAdminSession,
        string  $existingJson,
        int     $targetUserId
    ): ?string {
        if ($role !== 'admin') {
            return null; // Non-admins have no permissions column value
        }
        if ($targetUserId === 1) {
            return json_encode(self::MODULES); // Super Admin always has all
        }
        if ($isSelf && !$isSuperAdminSession) {
            return $existingJson; // Prevent self-escalation
        }
        return json_encode($this->sanitizePermissions($submittedPermissions));
    }

    public function createUser(array $data): int
    {
        $permissions = null;
        if (($data['role'] ?? 'user') === 'admin') {
            $permissions = json_encode($this->sanitizePermissions($data['permissions'] ?? []));
        }
        return $this->repo->save([
            'username'    => $data['username'],
            'email'       => $data['email'],
            'last_name'   => $data['last_name'],
            'first_name'  => $data['first_name'],
            'password'    => password_hash($data['password'], PASSWORD_DEFAULT),
            'role'        => $data['role'] ?? 'user',
            'permissions' => $permissions,
            'is_active'   => (int)($data['is_active'] ?? 1),
        ]);
    }

    public function updateUser(
        int   $id,
        array $data,
        bool  $isSelf,
        bool  $isSuperAdminSession
    ): bool {
        $existing = $this->repo->findById($id);
        $permissionsJson = $this->buildPermissionsJson(
            $data['role'] ?? 'user',
            $data['permissions'] ?? [],
            $isSelf,
            $isSuperAdminSession,
            $existing?->getPermissions() ? json_encode($existing->getPermissions()) : '[]',
            $id
        );
        return $this->repo->update($id, [
            'username'    => $data['username'],
            'email'       => $data['email'],
            'last_name'   => $data['last_name'],
            'first_name'  => $data['first_name'],
            'role'        => $data['role'] ?? 'user',
            'permissions' => $permissionsJson,
            'is_active'   => (int)($data['is_active'] ?? 1),
        ]);
    }
}
