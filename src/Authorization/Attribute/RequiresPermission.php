<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RequiresPermission
{
    public function __construct(
        public string $permission,
        public ?string $subject = null,
    ) {
    }
}
