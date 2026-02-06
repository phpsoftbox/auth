<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Auth\Contracts\UserInterface;

interface TokenProviderInterface
{
    public function retrieveUserByToken(string $token): ?UserInterface;
}
