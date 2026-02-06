<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use RuntimeException;

use function is_string;
use function password_hash;
use function password_verify;

use const PASSWORD_DEFAULT;

final class NativePasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plain): string
    {
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
