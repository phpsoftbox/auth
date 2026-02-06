<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

interface UserProviderInterface
{
    public function retrieveById(int|string $identifier): mixed;

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): mixed;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(mixed $user, array $credentials): bool;

    public function getUserId(mixed $user): int|string|null;
}
