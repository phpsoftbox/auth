<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store;

interface RoleStoreInterface
{
    public function findIdByName(string $name): ?int;

    public function create(string $name, ?string $label = null, bool $adminAccess = false): int;

    public function update(string $name, ?string $label = null, bool $adminAccess = false): void;

    /**
     * @return array<string, int>
     */
    public function listIdsByName(): array;

    /**
     * @param list<int> $ids
     */
    public function deleteByIds(array $ids): void;
}
