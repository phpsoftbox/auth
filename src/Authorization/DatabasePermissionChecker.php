<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

use function implode;
use function is_int;
use function is_string;
use function sprintf;
use function trim;

final class DatabasePermissionChecker implements PermissionCheckerInterface
{
    /**
     * @var array<string, true>|null
     */
    private ?array $allowAllRoles = null;

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
        private readonly ?RoleDefinitionProviderInterface $roleDefinitions = null,
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
            $userId = $user->id();
        } elseif ($user instanceof UserDataInterface) {
            $userId = $user->get($this->userIdField);
        } elseif (is_int($user) || is_string($user)) {
            $userId = $user;
        }

        if (!is_int($userId) && !is_string($userId)) {
            return false;
        }

        $conn             = $this->connections->read($this->connectionName);
        $permissionsTable = $conn->table($this->permissionsTable);
        $userRolesTable   = $conn->table($this->userRolesTable);
        $rolesTable       = $conn->table($this->rolesTable);

        if ($this->hasAllowAllRole($userRolesTable, $rolesTable, $userId)) {
            return true;
        }

        if ($this->hasDirectPermission($conn->table($this->userPermissionsTable), $permissionsTable, $userId, $permission)) {
            return true;
        }

        if ($this->hasRolePermission(
            $userRolesTable,
            $rolesTable,
            $conn->table($this->rolePermissionsTable),
            $permissionsTable,
            $userId,
            $permission,
        )) {
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

    private function hasAllowAllRole(string $userRoles, string $roles, int|string $userId): bool
    {
        $allowAllRoles = $this->allowAllRoles();
        if ($allowAllRoles === []) {
            return false;
        }

        $params     = ['user_id' => $userId];
        $conditions = [];
        $index      = 0;

        foreach ($allowAllRoles as $roleName => $_allowAll) {
            $param          = 'role_' . $index;
            $conditions[]   = 'r.name = :' . $param;
            $params[$param] = $roleName;
            $index++;
        }

        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT 1 FROM %s ur JOIN %s r ON r.id = ur.role_id WHERE ur.user_id = :user_id AND (%s) LIMIT 1',
            $userRoles,
            $roles,
            implode(' OR ', $conditions),
        );

        return $conn->fetchOne($sql, $params) !== null;
    }

    /**
     * @return array<string, true>
     */
    private function allowAllRoles(): array
    {
        if ($this->allowAllRoles !== null) {
            return $this->allowAllRoles;
        }

        if (!$this->roleDefinitions instanceof RoleDefinitionProviderInterface) {
            $this->allowAllRoles = [];

            return $this->allowAllRoles;
        }

        $allowAllRoles = [];
        foreach ($this->roleDefinitions->load()->roles as $role) {
            if (!$role->allowsAll()) {
                continue;
            }

            $roleName = trim($role->name);
            if ($roleName === '') {
                continue;
            }

            $allowAllRoles[$roleName] = true;
        }

        $this->allowAllRoles = $allowAllRoles;

        return $this->allowAllRoles;
    }
}
