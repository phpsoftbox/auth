<?php

declare(strict_types=1);

namespace PhpSoftBox\Pagination\Contracts;

interface PaginationResultInterface
{
    /**
     * @return list<mixed>
     */
    public function data(): array;

    /**
     * @return array<string, string|null>
     */
    public function links(): array;

    /**
     * @return array<string, mixed>
     */
    public function meta(): array;

    /**
     * @return array{data:list<mixed>,links:array<string, string|null>,meta:array<string, mixed>}
     */
    public function toArray(): array;
}
