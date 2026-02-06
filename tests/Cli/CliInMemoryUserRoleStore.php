<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Cli;

use PhpSoftBox\Auth\Authorization\Store\UserRoleStoreInterface;

use function array_filter;
use function array_values;
use function in_array;

final class CliInMemoryUserRoleStore implements UserRoleStoreInterface
{
    /**
     * @var array<int|string, list<int>>
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
    public function listRoleIdsByUserId(int|string $userId): array
    {
        return array_values($this->rolesByUser[$userId] ?? []);
    }

    /**
     * @return list<string>
     */
    public function listRoleNamesByUserId(int|string $userId): array
    {
        $roles = [];
        foreach ($this->listRoleIdsByUserId($userId) as $roleId) {
            $name = $this->namesById[$roleId] ?? null;
            if ($name !== null) {
                $roles[] = $name;
            }
        }

        return $roles;
    }

    public function attach(int|string $userId, int $roleId): void
    {
        $current = $this->rolesByUser[$userId] ?? [];
        if (!in_array($roleId, $current, true)) {
            $current[] = $roleId;
        }

        $this->rolesByUser[$userId] = $current;
    }

    public function detach(int|string $userId, int $roleId): void
    {
        if (!isset($this->rolesByUser[$userId])) {
            return;
        }

        $this->rolesByUser[$userId] = array_values(array_filter(
            $this->rolesByUser[$userId],
            static fn (int $id): bool => $id !== $roleId,
        ));
    }

    public function detachAll(int|string $userId): void
    {
        unset($this->rolesByUser[$userId]);
    }
}
