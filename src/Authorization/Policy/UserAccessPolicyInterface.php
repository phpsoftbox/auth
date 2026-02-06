<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Policy;

use PhpSoftBox\Auth\Authorization\AccessDecision;

interface UserAccessPolicyInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function decide(
        mixed $initiator,
        string $permission,
        mixed $subject = null,
        array $context = [],
    ): AccessDecision;
}
