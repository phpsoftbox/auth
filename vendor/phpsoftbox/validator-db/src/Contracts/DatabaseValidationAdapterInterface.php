<?php

declare(strict_types=1);

namespace PhpSoftBox\Validator\Db\Contracts;

interface DatabaseValidationAdapterInterface
{
    /**
     * Проверяет существование записи по критериям.
     *
     * @param array<string, mixed> $criteria
     */
    public function exists(string $table, array $criteria, ?string $connection = null): bool;

    /**
     * Проверяет уникальность записи по критериям.
     *
     * @param array<string, mixed> $criteria
     */
    public function unique(
        string $table,
        array $criteria,
        ?string $connection = null,
        ?string $ignoreColumn = null,
        mixed $ignoreValue = null,
    ): bool;
}
