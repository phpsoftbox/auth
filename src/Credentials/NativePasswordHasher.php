<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use function password_hash;
use function password_verify;

use const PASSWORD_DEFAULT;

final class NativePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
