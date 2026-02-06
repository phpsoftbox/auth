<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\AccountProtection;

final readonly class AccountProtectionScopeConfig
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $captchaSeconds = 900,
    ) {
    }
}
