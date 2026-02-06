<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use PhpSoftBox\Auth\Contracts\UserInterface;

interface CredentialsValidatorInterface
{
    /**
     * @param array<string, mixed> $credentials
     */
    public function supports(array $credentials): bool;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validate(UserInterface $user, array $credentials): bool;
}
