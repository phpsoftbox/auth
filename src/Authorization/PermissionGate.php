<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final class PermissionGate implements PermissionCheckerInterface
{
    public function __construct(
        private readonly PermissionCheckerInterface $checker,
        private readonly PermissionPolicyRegistry $policies = new PermissionPolicyRegistry(),
    ) {
    }

    public function can(mixed $user, string $permission, mixed $subject = null): bool
    {
        if (!$this->checker->can($user, $permission, $subject)) {
            return false;
        }

        return $this->policies->allows($user, $permission, $subject);
    }
}
