<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Cli;

use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;

use function array_values;
use function in_array;
use function max;

final class CliInMemoryRoleStore implements RoleStoreInterface
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
            $this->roles[$name] = ['id' => $this->nextId(), 'label' => $label, 'admin_access' => $adminAccess];

            return;
        }

        $this->roles[$name]['label']        = $label;
        $this->roles[$name]['admin_access'] = $adminAccess;
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
        foreach ($this->roles as $name => $data) {
            if (in_array($data['id'], $ids, true)) {
                unset($this->roles[$name]);
            }
        }
    }

    private function nextId(): int
    {
        if ($this->roles === []) {
            return 1;
        }

        $ids = [];
        foreach ($this->roles as $data) {
            $ids[] = $data['id'];
        }

        return max(array_values($ids)) + 1;
    }
}
