<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\RolePermissionStoreInterface;

use function array_filter;
use function array_values;
use function in_array;

final class InMemoryRolePermissionStore implements RolePermissionStoreInterface
{
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

        $this->map[$roleId] = array_values(array_filter(
            $this->map[$roleId],
            static fn (int $id): bool => $id !== $permissionId,
        ));
    }

    public function detachByRoleId(int $roleId): void
    {
        unset($this->map[$roleId]);
    }

    public function detachByPermissionId(int $permissionId): void
    {
        foreach ($this->map as $roleId => $ids) {
            $this->map[$roleId] = array_values(array_filter(
                $ids,
                static fn (int $id): bool => $id !== $permissionId,
            ));
        }
    }
}
