<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use Closure;
use PhpSoftBox\Auth\Contracts\UserInterface;

use function array_key_exists;
use function is_string;

final class PasswordCredentialsValidator implements CredentialsValidatorInterface
{
    private PasswordHasherInterface $hasher;
    private readonly Closure $passwordHashResolver;

    public function __construct(
        callable $passwordHashResolver,
        ?PasswordHasherInterface $hasher = null,
        private readonly string $credentialKey = 'password',
    ) {
        $this->passwordHashResolver = $passwordHashResolver(...);
        $this->hasher               = $hasher ?? new NativePasswordHasher();
    }

    public function supports(array $credentials): bool
    {
        return array_key_exists($this->credentialKey, $credentials);
    }

    public function validate(UserInterface $user, array $credentials): bool
    {
        $password = $credentials[$this->credentialKey] ?? null;
        if (!is_string($password)) {
            return false;
        }

        $hash = ($this->passwordHashResolver)($user);
        if (!is_string($hash) || $hash === '') {
            return false;
        }

        return $this->hasher->verify($password, $hash);
    }
}
