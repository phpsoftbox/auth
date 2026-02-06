<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class PermissionGrant
{
    /**
     * @param list<PermissionActionEnum|string>|null $actions
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $resource,
        public ?array $actions = null,
        public ?string $scope = null,
        public array $meta = [],
    ) {
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
