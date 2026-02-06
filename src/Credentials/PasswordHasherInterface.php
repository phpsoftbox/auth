<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

interface PasswordHasherInterface
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;
}
