<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Auth\Contracts\UserInterface;

interface UserProviderInterface
{
    public function retrieveById(int|string $identifier): ?UserInterface;

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): ?UserInterface;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(UserInterface $user, array $credentials): bool;
}
