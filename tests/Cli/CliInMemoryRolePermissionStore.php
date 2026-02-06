<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Cli;

use PhpSoftBox\Auth\Authorization\Store\RolePermissionStoreInterface;

use function in_array;

final class CliInMemoryRolePermissionStore implements RolePermissionStoreInterface
{
    /** @var array<int, list<int>> */
    private array $map = [];

    public function listPermissionIds(int $roleId): array
    {
        return $this->map[$roleId] ?? [];
    }

    public function attach(int $roleId, int $permissionId): void
    {
        $this->map[$roleId] ??= [];
        if (!in_array($permissionId, $this->map[$roleId], true)) {
            $this->map[$roleId][] = $permissionId;
        }
    }

    public function detach(int $roleId, int $permissionId): void
    {
        if (!isset($this->map[$roleId])) {
            return;
        }

        $filtered = [];
        foreach ($this->map[$roleId] as $id) {
            if ($id !== $permissionId) {
                $filtered[] = $id;
            }
        }
        $this->map[$roleId] = $filtered;
    }

    public function detachByRoleId(int $roleId): void
    {
        unset($this->map[$roleId]);
    }

    public function detachByPermissionId(int $permissionId): void
    {
        foreach ($this->map as $roleId => $ids) {
            $filtered = [];
            foreach ($ids as $id) {
                if ($id !== $permissionId) {
                    $filtered[] = $id;
                }
            }
            $this->map[$roleId] = $filtered;
        }
    }
}
