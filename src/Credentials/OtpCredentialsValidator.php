<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use Closure;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Otp\OtpValidatorInterface;

use function array_key_exists;
use function is_string;

final class OtpCredentialsValidator implements CredentialsValidatorInterface
{
    private readonly Closure $identifierResolver;

    public function __construct(
        private readonly OtpValidatorInterface $validator,
        callable $identifierResolver,
        private readonly string $credentialKey = 'otp',
    ) {
        $this->identifierResolver = $identifierResolver(...);
    }

    public function supports(array $credentials): bool
    {
        return array_key_exists($this->credentialKey, $credentials);
    }

    public function validate(UserInterface $user, array $credentials): bool
    {
        $code = $credentials[$this->credentialKey] ?? null;
        if (!is_string($code) || $code === '') {
            return false;
        }

        $identifier = ($this->identifierResolver)($user);
        if (!is_string($identifier) || $identifier === '') {
            return false;
        }

        return $this->validator->validate($identifier, $code);
    }
}
