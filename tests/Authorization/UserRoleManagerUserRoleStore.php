<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\UserRoleStoreInterface;

use function array_filter;
use function array_values;
use function in_array;

final class UserRoleManagerUserRoleStore implements UserRoleStoreInterface
{
    /**
     * @var array<int, list<int>>
     */
    private array $rolesByUser = [];

    /**
     * @var array<int, string>
     */
    private array $namesById = [];

    public function registerRole(int $roleId, string $name): void
    {
        $this->namesById[$roleId] = $name;
    }

    /**
     * @return list<int>
     */
    public function listRoleIdsByUserId(int $userId): array
    {
        return array_values($this->rolesByUser[$userId] ?? []);
    }

    /**
     * @return list<string>
     */
    public function listRoleNamesByUserId(int $userId): array
    {
        $roleIds = $this->listRoleIdsByUserId($userId);
        $names   = [];
        foreach ($roleIds as $roleId) {
            $name = $this->namesById[$roleId] ?? null;
            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function attach(int $userId, int $roleId): void
    {
        $current = $this->rolesByUser[$userId] ?? [];
        if (!in_array($roleId, $current, true)) {
            $current[] = $roleId;
        }
        $this->rolesByUser[$userId] = $current;
    }

    public function detach(int $userId, int $roleId): void
    {
        if (!isset($this->rolesByUser[$userId])) {
            return;
        }

        $this->rolesByUser[$userId] = array_values(
            array_filter(
                $this->rolesByUser[$userId],
                fn (int $id): bool => $id !== $roleId,
            ),
        );
    }

    public function detachAll(int $userId): void
    {
        unset($this->rolesByUser[$userId]);
    }
}
