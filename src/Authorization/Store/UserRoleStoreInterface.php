<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store;

interface UserRoleStoreInterface
{
    /**
     * @return list<int>
     */
    public function listRoleIdsByUserId(int $userId): array;

    /**
     * @return list<string>
     */
    public function listRoleNamesByUserId(int $userId): array;

    public function attach(int $userId, int $roleId): void;

    public function detach(int $userId, int $roleId): void;

    public function detachAll(int $userId): void;
}
