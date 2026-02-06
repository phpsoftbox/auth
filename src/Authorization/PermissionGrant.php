<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class PermissionGrant
{
    /**
     * @param list<PermissionActionEnum|string>|null $actions
     */
    public function __construct(
        public string $resource,
        public ?array $actions = null,
        public ?string $scope = null,
    ) {
    }
}
