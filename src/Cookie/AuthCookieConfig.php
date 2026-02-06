<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Cookie;

use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Session\CookieSecurePolicy;

/**
 * @deprecated Use PhpSoftBox\Auth\Remember\RememberCookieConfig for browser remember-me cookies.
 */
final readonly class AuthCookieConfig
{
    public function __construct(
        public string $name = 'auth_token',
        public string $path = '/',
        public ?string $domain = null,
        public CookieSecurePolicy $secure = CookieSecurePolicy::Always,
        public bool $httpOnly = true,
        public ?SameSite $sameSite = SameSite::Lax,
        public ?int $maxAge = null,
    ) {
    }
}
