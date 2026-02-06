<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use PhpSoftBox\Auth\Contracts\UserDataInterface;

use function array_key_exists;
use function is_string;

final class PasswordCredentialsValidator implements CredentialsValidatorInterface
{
    private PasswordHasherInterface $hasher;

    public function __construct(
        ?PasswordHasherInterface $hasher = null,
        private readonly string $passwordField = 'password_hash',
        private readonly string $credentialKey = 'password',
    ) {
        $this->hasher = $hasher ?? new NativePasswordHasher();
    }

    public function supports(array $credentials): bool
    {
        return array_key_exists($this->credentialKey, $credentials);
    }

    public function validate(mixed $user, array $credentials): bool
    {
        if (!$user instanceof UserDataInterface) {
            return false;
        }

        $password = $credentials[$this->credentialKey] ?? null;
        if (!is_string($password)) {
            return false;
        }

        $hash = $user->get($this->passwordField);
        if (!is_string($hash) || $hash === '') {
            return false;
        }

        return $this->hasher->verify($password, $hash);
    }
}
