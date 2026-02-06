<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\AccountProtection;

final readonly class AccountProtectionConfig
{
    /**
     * @param array<string, AccountProtectionScopeConfig> $scopes
     */
    public function __construct(
        public int $maxAttempts = 3,
        public int $captchaSeconds = 900,
        public string $cachePrefix = 'auth.account_protection',
        public string $captchaSiteKey = '',
        public string $captchaSecretKey = '',
        public string $captchaVerifyUrl = 'https://smartcaptcha.yandexcloud.net/validate',
        public array $scopes = [],
    ) {
    }
}
