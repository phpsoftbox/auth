<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

use function is_int;
use function is_string;
use function sprintf;
use function trim;

final class DatabasePermissionChecker implements PermissionCheckerInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $permissionsTable = 'permissions',
        private readonly string $rolesTable = 'roles',
        private readonly string $rolePermissionsTable = 'role_permissions',
        private readonly string $userPermissionsTable = 'user_permissions',
        private readonly string $userRolesTable = 'user_roles',
        private readonly string $userIdField = 'id',
        private readonly string $permissionNameField = 'name',
        private readonly string $roleAdminAccessField = 'admin_access',
        private readonly string $adminPermission = 'admin.access',
    ) {
    }

    public function can(mixed $user, string $permission, mixed $subject = null): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        $userId = null;
        if ($user instanceof UserIdentityInterface) {
            $userId = $user->getId();
        } elseif ($user instanceof UserDataInterface) {
            $userId = $user->get($this->userIdField);
        } elseif (is_int($user) || is_string($user)) {
            $userId = $user;
        }

        if (!is_int($userId) && !is_string($userId)) {
            return false;
        }

        $conn = $this->connections->read($this->connectionName);

        if ($this->hasDirectPermission($conn->table($this->userPermissionsTable), $conn->table($this->permissionsTable), $userId, $permission)) {
            return true;
        }

        if ($this->hasRolePermission(
            $conn->table($this->userRolesTable),
            $conn->table($this->rolesTable),
            $conn->table($this->rolePermissionsTable),
            $conn->table($this->permissionsTable),
            $userId,
            $permission,
        )) {
            return true;
        }

        if ($permission === $this->adminPermission && $this->hasAdminRole($conn->table($this->userRolesTable), $conn->table($this->rolesTable), $userId)) {
            return true;
        }

        return false;
    }

    private function hasDirectPermission(string $userPermissions, string $permissions, int|string $userId, string $permission): bool
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT 1 FROM %s up JOIN %s p ON p.id = up.permission_id WHERE up.user_id = :user_id AND p.%s = :perm LIMIT 1',
            $userPermissions,
            $permissions,
            $this->permissionNameField,
        );

        return $conn->fetchOne($sql, ['user_id' => $userId, 'perm' => $permission]) !== null;
    }

    private function hasRolePermission(
        string $userRoles,
        string $roles,
        string $rolePermissions,
        string $permissions,
        int|string $userId,
        string $permission,
    ): bool {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT 1 FROM %s ur JOIN %s r ON r.id = ur.role_id JOIN %s rp ON rp.role_id = r.id JOIN %s p ON p.id = rp.permission_id WHERE ur.user_id = :user_id AND p.%s = :perm LIMIT 1',
            $userRoles,
            $roles,
            $rolePermissions,
            $permissions,
            $this->permissionNameField,
        );

        return $conn->fetchOne($sql, ['user_id' => $userId, 'perm' => $permission]) !== null;
    }

    private function hasAdminRole(string $userRoles, string $roles, int|string $userId): bool
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT 1 FROM %s ur JOIN %s r ON r.id = ur.role_id WHERE ur.user_id = :user_id AND r.%s = 1 LIMIT 1',
            $userRoles,
            $roles,
            $this->roleAdminAccessField,
        );

        return $conn->fetchOne($sql, ['user_id' => $userId]) !== null;
    }
}
