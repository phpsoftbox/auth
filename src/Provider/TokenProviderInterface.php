<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

interface TokenProviderInterface
{
    public function retrieveUserByToken(string $token): mixed;
}
