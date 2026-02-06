<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

interface CredentialsValidatorInterface
{
    /**
     * @param array<string, mixed> $credentials
     */
    public function supports(array $credentials): bool;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validate(mixed $user, array $credentials): bool;
}
