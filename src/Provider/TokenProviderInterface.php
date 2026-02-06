<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

interface TokenProviderInterface
{
    public function retrieveUserIdByToken(string $token): int|string|null;
}
