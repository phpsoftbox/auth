<?php

declare(strict_types=1);

namespace PhpSoftBox\Pagination\Contracts;

interface PaginationContextResolverInterface
{
    public function path(): ?string;

    public function fragment(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function query(): array;

    public function page(): ?int;

    public function perPage(): ?int;
}
