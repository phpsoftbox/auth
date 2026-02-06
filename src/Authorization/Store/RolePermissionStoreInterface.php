<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store;

interface RolePermissionStoreInterface
{
    /**
     * @return list<int>
     */
    public function listPermissionIds(int $roleId): array;

    public function attach(int $roleId, int $permissionId): void;

    public function detach(int $roleId, int $permissionId): void;

    public function detachByRoleId(int $roleId): void;

    public function detachByPermissionId(int $permissionId): void;
}
