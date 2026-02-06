<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Attribute;

use Attribute;
use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequiresAllPermissions
{
    /**
     * @param list<PermissionCase> $cases
     */
    public function __construct(
        public array $cases,
        public PermissionDeniedMode $deniedMode = PermissionDeniedMode::Forbidden,
    ) {
    }
}
