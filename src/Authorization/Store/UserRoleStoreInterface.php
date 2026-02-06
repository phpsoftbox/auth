<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store;

interface UserRoleStoreInterface
{
    /**
     * @return list<int>
     */
    public function listRoleIdsByUserId(int|string $userId): array;

    /**
     * @return list<string>
     */
    public function listRoleNamesByUserId(int|string $userId): array;

    public function attach(int|string $userId, int $roleId): void;

    public function detach(int|string $userId, int $roleId): void;

    public function detachAll(int|string $userId): void;
}
