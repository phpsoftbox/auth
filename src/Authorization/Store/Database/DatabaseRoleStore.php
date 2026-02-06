<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store\Database;

use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

final class DatabaseRoleStore implements RoleStoreInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'roles',
        private readonly string $nameField = 'name',
        private readonly string $labelField = 'label',
        private readonly string $adminAccessField = 'admin_access',
    ) {
    }

    public function findIdByName(string $name): ?int
    {
        $conn = $this->connections->read($this->connectionName);
        $row  = $conn->fetchOne(
            "SELECT id FROM {$this->table} WHERE {$this->nameField} = :name LIMIT 1",
            ['name' => $name],
        );

        return $row === null ? null : (int) $row['id'];
    }

    public function create(string $name, ?string $label = null, bool $adminAccess = false): int
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->insert($this->table, [
                $this->nameField        => $name,
                $this->labelField       => $label,
                $this->adminAccessField => $adminAccess ? 1 : 0,
            ])
            ->execute();

        return (int) $conn->lastInsertId();
    }

    public function update(string $name, ?string $label = null, bool $adminAccess = false): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->update($this->table, [
                $this->labelField       => $label,
                $this->adminAccessField => $adminAccess ? 1 : 0,
            ])
            ->where($this->nameField . ' = :name', ['name' => $name])
            ->execute();
    }

    public function listIdsByName(): array
    {
        $conn = $this->connections->read($this->connectionName);
        $rows = $conn->fetchAll(
            "SELECT id, {$this->nameField} FROM {$this->table}",
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row[$this->nameField]] = (int) $row['id'];
        }

        return $map;
    }

    public function deleteByIds(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->delete($this->table)
            ->whereIn('id', $ids)
            ->execute();
    }
}
