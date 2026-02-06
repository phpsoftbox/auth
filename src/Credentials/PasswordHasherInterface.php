<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

interface PasswordHasherInterface
{
    public function verify(string $plain, string $hash): bool;
}
