<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Session\CookieSecurePolicy;

final readonly class RememberCookieConfig
{
    public function __construct(
        public string $name = 'remember_token',
        public string $path = '/',
        public ?string $domain = null,
        public CookieSecurePolicy $secure = CookieSecurePolicy::Always,
        public bool $httpOnly = true,
        public ?SameSite $sameSite = SameSite::Lax,
        public ?int $maxAge = null,
    ) {
    }
}
