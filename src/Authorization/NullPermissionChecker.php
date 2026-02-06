<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

final class NullPermissionChecker implements PermissionCheckerInterface
{
    public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
    {
        return false;
    }
}
