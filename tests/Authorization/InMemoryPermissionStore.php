<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\PermissionStoreInterface;

use function array_keys;
use function array_unique;
use function array_values;
use function in_array;

class InMemoryPermissionStore implements PermissionStoreInterface
{
    private int $nextId        = 1;
    private array $permissions = [];

    public function findIdByName(string $name): ?int
    {
        return $this->permissions[$name]['id'] ?? null;
    }

    public function create(string $name, ?string $label = null): int
    {
        $id                       = $this->nextId++;
        $this->permissions[$name] = ['id' => $id, 'label' => $label];

        return $id;
    }

    public function updateLabel(int $id, ?string $label = null): void
    {
        foreach ($this->permissions as $name => $data) {
            if ($data['id'] === $id) {
                $this->permissions[$name]['label'] = $label;

                return;
            }
        }
    }

    public function names(): array
    {
        return array_keys($this->permissions);
    }

    public function listIdsByName(): array
    {
        $map = [];
        foreach ($this->permissions as $name => $data) {
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

        foreach ($this->permissions as $name => $data) {
            if (in_array($data['id'], $ids, true)) {
                unset($this->permissions[$name]);
            }
        }
    }
}
