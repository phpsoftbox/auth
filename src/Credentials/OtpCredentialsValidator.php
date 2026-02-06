<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use PhpSoftBox\Auth\Otp\OtpValidatorInterface;
use PhpSoftBox\Auth\Support\UserAccessor;

use function array_key_exists;
use function is_string;

final class OtpCredentialsValidator implements CredentialsValidatorInterface
{
    public function __construct(
        private readonly OtpValidatorInterface $validator,
        private readonly string $credentialKey = 'otp',
        private readonly string $identifierField = 'phone_number',
    ) {
    }

    public function supports(array $credentials): bool
    {
        return array_key_exists($this->credentialKey, $credentials);
    }

    public function validate(mixed $user, array $credentials): bool
    {
        $code = $credentials[$this->credentialKey] ?? null;
        if (!is_string($code) || $code === '') {
            return false;
        }

        $identifier = UserAccessor::get($user, $this->identifierField);
        if (!is_string($identifier) || $identifier === '') {
            return false;
        }

        return $this->validator->validate($identifier, $code);
    }
}
