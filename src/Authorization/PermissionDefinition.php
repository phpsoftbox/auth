<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class PermissionDefinition
{
    public function __construct(
        public string $name,
        public ?string $label = null,
    ) {
    }
}
