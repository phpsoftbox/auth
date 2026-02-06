<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store;

interface PermissionStoreInterface
{
    public function findIdByName(string $name): ?int;

    public function create(string $name, ?string $label = null): int;

    public function updateLabel(int $id, ?string $label = null): void;

    /**
     * @return array<string, int>
     */
    public function listIdsByName(): array;

    /**
     * @param list<int> $ids
     */
    public function deleteByIds(array $ids): void;
}
