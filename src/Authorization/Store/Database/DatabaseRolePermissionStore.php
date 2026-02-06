<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store\Database;

use PhpSoftBox\Auth\Authorization\Store\RolePermissionStoreInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

final class DatabaseRolePermissionStore implements RolePermissionStoreInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'role_permissions',
    ) {
    }

    public function listPermissionIds(int $roleId): array
    {
        $conn = $this->connections->read($this->connectionName);
        $rows = $conn->fetchAll(
            "SELECT permission_id FROM {$this->table} WHERE role_id = :role_id",
            ['role_id' => $roleId],
        );

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['permission_id'];
        }

        return $ids;
    }

    public function attach(int $roleId, int $permissionId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->insert($this->table, [
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ])
            ->execute();
    }

    public function detach(int $roleId, int $permissionId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->delete($this->table)
            ->where('role_id = :role_id', ['role_id' => $roleId])
            ->where('permission_id = :permission_id', ['permission_id' => $permissionId])
            ->execute();
    }

    public function detachByRoleId(int $roleId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->delete($this->table)
            ->where('role_id = :role_id', ['role_id' => $roleId])
            ->execute();
    }

    public function detachByPermissionId(int $permissionId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->delete($this->table)
            ->where('permission_id = :permission_id', ['permission_id' => $permissionId])
            ->execute();
    }
}
