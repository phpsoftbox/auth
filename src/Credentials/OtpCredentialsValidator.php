<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Credentials;

use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Otp\OtpValidatorInterface;

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
        if (!$user instanceof UserDataInterface) {
            return false;
        }

        $code = $credentials[$this->credentialKey] ?? null;
        if (!is_string($code) || $code === '') {
            return false;
        }

        $identifier = $user->get($this->identifierField);
        if (!is_string($identifier) || $identifier === '') {
            return false;
        }

        return $this->validator->validate($identifier, $code);
    }
}
