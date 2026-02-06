<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

final class NativePasswordHasher implements PasswordHasherInterface
{
    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
