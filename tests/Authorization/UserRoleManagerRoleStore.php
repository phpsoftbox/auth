<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;

use function array_keys;
use function array_values;
use function in_array;
use function max;

final class UserRoleManagerRoleStore implements RoleStoreInterface
{
    /**
     * @var array<string, int>
     */
    private array $idsByName = [];

    public function add(string $name, int $id): void
    {
        $this->idsByName[$name] = $id;
    }

    public function findIdByName(string $name): ?int
    {
        return $this->idsByName[$name] ?? null;
    }

    public function create(string $name, ?string $label = null, bool $adminAccess = false): int
    {
        $id                     = $this->nextId();
        $this->idsByName[$name] = $id;

        return $id;
    }

    public function update(string $name, ?string $label = null, bool $adminAccess = false): void
    {
        if (!isset($this->idsByName[$name])) {
            $this->idsByName[$name] = $this->nextId();
        }
    }

    /**
     * @return array<string, int>
     */
    public function listIdsByName(): array
    {
        return $this->idsByName;
    }

    /**
     * @param list<int> $ids
     */
    public function deleteByIds(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $current         = $this->idsByName;
        $names           = array_keys($current);
        $this->idsByName = [];

        foreach ($names as $name) {
            if (!in_array($current[$name], $ids, true)) {
                $this->idsByName[$name] = $current[$name];
            }
        }
    }

    private function nextId(): int
    {
        if ($this->idsByName === []) {
            return 1;
        }

        return max(array_values($this->idsByName)) + 1;
    }
}
