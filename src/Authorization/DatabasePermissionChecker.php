<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;

use function array_values;
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

    /**
     * @var array<string, array{
     *     role_ids: list<int|string>,
     *     role_names: array<string, true>,
     *     direct_permissions: array<string, true>,
     *     role_permissions: array<string, true>,
     *     allow_all: bool
     * }>
     */
    private array $grantSnapshots = [];

    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $permissionsTable = 'permissions',
        private readonly string $rolesTable = 'roles',
        private readonly string $rolePermissionsTable = 'role_permissions',
        private readonly string $userPermissionsTable = 'user_permissions',
        private readonly string $userRolesTable = 'user_roles',
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

        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return false;
        }

        $snapshot = $this->grantSnapshot($userId);
        if ($snapshot['allow_all']) {
            return true;
        }

        return isset($snapshot['direct_permissions'][$permission])
            || isset($snapshot['role_permissions'][$permission]);
    }

    public function reset(): void
    {
        $this->allowAllRoles  = null;
        $this->grantSnapshots = [];
    }

    private function resolveUserId(mixed $user): int|string|null
    {
        $userId = null;
        if ($user instanceof UserInterface) {
            $userId = $user->id();
        } elseif (is_int($user) || is_string($user)) {
            $userId = $user;
        }

        if (!is_int($userId) && !is_string($userId)) {
            return null;
        }

        return $userId;
    }

    /**
     * @return array{
     *     role_ids: list<int|string>,
     *     role_names: array<string, true>,
     *     direct_permissions: array<string, true>,
     *     role_permissions: array<string, true>,
     *     allow_all: bool
     * }
     */
    private function grantSnapshot(int|string $userId): array
    {
        $conn = $this->connections->read($this->connectionName);
        $key  = $this->snapshotCacheKey($userId);
        if (isset($this->grantSnapshots[$key])) {
            return $this->grantSnapshots[$key];
        }

        $roles    = $this->loadUserRoles($conn, $userId);
        $allowAll = $this->hasAllowAllRole($roles['names']);

        $snapshot = [
            'role_ids'           => $roles['ids'],
            'role_names'         => $roles['names'],
            'direct_permissions' => [],
            'role_permissions'   => [],
            'allow_all'          => $allowAll,
        ];

        if (!$allowAll) {
            $snapshot['direct_permissions'] = $this->loadDirectPermissions($conn, $userId);
            $snapshot['role_permissions']   = $this->loadRolePermissions($conn, $roles['ids']);
        }

        $this->grantSnapshots[$key] = $snapshot;

        return $snapshot;
    }

    private function snapshotCacheKey(int|string $userId): string
    {
        return $this->connectionName
            . '|permissions=' . $this->permissionsTable
            . '|roles=' . $this->rolesTable
            . '|role_permissions=' . $this->rolePermissionsTable
            . '|user_permissions=' . $this->userPermissionsTable
            . '|user_roles=' . $this->userRolesTable
            . '|permission_name=' . $this->permissionNameField
            . '|user=' . (is_int($userId) ? 'int:' : 'string:') . $userId;
    }

    /**
     * @return array{ids: list<int|string>, names: array<string, true>}
     */
    private function loadUserRoles(ConnectionInterface $conn, int|string $userId): array
    {
        $sql = sprintf(
            'SELECT r.id AS role_id, r.name AS role_name FROM %s ur JOIN %s r ON r.id = ur.role_id WHERE ur.user_id = :user_id',
            $conn->table($this->userRolesTable),
            $conn->table($this->rolesTable),
        );

        $ids   = [];
        $names = [];

        foreach ($conn->fetchAll($sql, ['user_id' => $userId]) as $row) {
            $roleId = $row['role_id'] ?? null;
            if (is_int($roleId) || is_string($roleId)) {
                $ids[] = $roleId;
            }

            $roleName = trim((string) ($row['role_name'] ?? ''));
            if ($roleName !== '') {
                $names[$roleName] = true;
            }
        }

        return [
            'ids'   => array_values($ids),
            'names' => $names,
        ];
    }

    /**
     * @return array<string, true>
     */
    private function loadDirectPermissions(ConnectionInterface $conn, int|string $userId): array
    {
        $sql = sprintf(
            'SELECT p.%s AS permission_name FROM %s up JOIN %s p ON p.id = up.permission_id WHERE up.user_id = :user_id',
            $this->permissionNameField,
            $conn->table($this->userPermissionsTable),
            $conn->table($this->permissionsTable),
        );

        return $this->permissionRowsToSet($conn->fetchAll($sql, ['user_id' => $userId]));
    }

    /**
     * @param list<int|string> $roleIds
     * @return array<string, true>
     */
    private function loadRolePermissions(ConnectionInterface $conn, array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $params       = [];
        $placeholders = [];
        foreach ($roleIds as $index => $roleId) {
            $param          = 'role_' . $index;
            $placeholders[] = ':' . $param;
            $params[$param] = $roleId;
        }

        $sql = sprintf(
            'SELECT p.%s AS permission_name FROM %s rp JOIN %s p ON p.id = rp.permission_id WHERE rp.role_id IN (%s)',
            $this->permissionNameField,
            $conn->table($this->rolePermissionsTable),
            $conn->table($this->permissionsTable),
            implode(', ', $placeholders),
        );

        return $this->permissionRowsToSet($conn->fetchAll($sql, $params));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, true>
     */
    private function permissionRowsToSet(array $rows): array
    {
        $permissions = [];
        foreach ($rows as $row) {
            $permission = trim((string) ($row['permission_name'] ?? ''));
            if ($permission === '') {
                continue;
            }

            $permissions[$permission] = true;
        }

        return $permissions;
    }

    /**
     * @param array<string, true> $roleNames
     */
    private function hasAllowAllRole(array $roleNames): bool
    {
        $allowAllRoles = $this->allowAllRoles();
        if ($allowAllRoles === []) {
            return false;
        }

        foreach ($allowAllRoles as $roleName => $_allowAll) {
            if (isset($roleNames[$roleName])) {
                return true;
            }
        }

        return false;
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
