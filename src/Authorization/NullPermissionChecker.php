<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final class NullPermissionChecker implements PermissionCheckerInterface
{
    public function can(mixed $user, string $permission, mixed $subject = null): bool
    {
        return false;
    }
}
