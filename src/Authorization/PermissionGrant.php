<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

final readonly class PermissionGrant
{
    public string $resource;

    /**
     * @param list<BackedEnum|string>|null $actions
     * @param array<string, mixed> $meta
     */
    public function __construct(
        string|BackedEnum $resource,
        public ?array $actions = null,
        public ?string $scope = null,
        public array $meta = [],
    ) {
        $this->resource = PermissionName::normalize($resource);
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
