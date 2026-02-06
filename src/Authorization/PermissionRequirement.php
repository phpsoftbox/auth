<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class PermissionRequirement
{
    public function __construct(
        public string $permission,
        public ?string $subjectAttribute = null,
    ) {
    }
}
