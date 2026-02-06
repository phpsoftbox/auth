<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;

use function array_keys;
use function array_unique;
use function array_values;
use function in_array;

final class InMemoryRoleStore implements RoleStoreInterface
{
    private int $nextId  = 1;
    private array $roles = [];

    public function findIdByName(string $name): ?int
    {
        return $this->roles[$name]['id'] ?? null;
    }

    public function create(string $name, ?string $label = null, bool $adminAccess = false): int
    {
        $id                 = $this->nextId++;
        $this->roles[$name] = ['id' => $id, 'label' => $label, 'admin_access' => $adminAccess];

        return $id;
    }

    public function update(string $name, ?string $label = null, bool $adminAccess = false): void
    {
        if (!isset($this->roles[$name])) {
            return;
        }

        $this->roles[$name]['label']        = $label;
        $this->roles[$name]['admin_access'] = $adminAccess;
    }

    public function names(): array
    {
        return array_keys($this->roles);
    }

    public function listIdsByName(): array
    {
        $map = [];
        foreach ($this->roles as $name => $data) {
            $map[$name] = $data['id'];
        }

        return $map;
    }

    public function deleteByIds(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        foreach ($this->roles as $name => $data) {
            if (in_array($data['id'], $ids, true)) {
                unset($this->roles[$name]);
            }
        }
    }
}
