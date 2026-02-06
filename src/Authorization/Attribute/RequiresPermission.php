<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Attribute;

use Attribute;
use BackedEnum;
use PhpSoftBox\Auth\Authorization\PermissionName;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequiresPermission
{
    public string $permission;

    public function __construct(
        string|BackedEnum $permission,
        public ?string $subject = null,
    ) {
        $this->permission = PermissionName::normalize($permission);
    }
}
