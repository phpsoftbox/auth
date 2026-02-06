<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

final class PermissionGate implements PermissionDecisionCheckerInterface
{
    public function __construct(
        private readonly PermissionCheckerInterface $checker,
        private readonly PermissionPolicyRegistry $policies = new PermissionPolicyRegistry(),
    ) {
    }

    public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
    {
        return $this->decide($user, $permission, $subject)->isAllowed();
    }

    public function decide(mixed $user, string|BackedEnum $permission, mixed $subject = null): AccessDecision
    {
        $permission = PermissionName::normalize($permission);

        $decision = $this->checker instanceof PermissionDecisionCheckerInterface
            ? $this->checker->decide($user, $permission, $subject)
            : ($this->checker->can($user, $permission, $subject)
                ? AccessDecision::allow()
                : AccessDecision::deny('Permission denied: ' . $permission));

        if (!$decision->isAllowed()) {
            return $decision;
        }

        return $this->policies->decide($user, $permission, $subject);
    }
}
