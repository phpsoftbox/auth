<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

interface PermissionDecisionCheckerInterface extends PermissionCheckerInterface
{
    public function decide(mixed $user, string|BackedEnum $permission, mixed $subject = null): AccessDecision;
}
