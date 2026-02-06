<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\UserProviderInterface;

final readonly class RememberGuardConfig
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public DatabaseRememberTokenStore $store,
        public RememberCookieManager $cookies,
        public RememberTokenExtractor $extractor,
        public UserProviderInterface $users,
        public SessionGuard $guard,
        public array $metadata = [],
    ) {
    }
}
