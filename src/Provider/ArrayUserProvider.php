<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\Support\UserAccessor;

use function array_key_exists;
use function is_int;
use function is_string;

class ArrayUserProvider implements UserProviderInterface
{
    /** @var list<CredentialsValidatorInterface> */
    private array $validators;

    /**
     * @param list<array<string, mixed>|object> $users
     * @param list<string> $loginFields
     * @param list<CredentialsValidatorInterface>|null $validators
     */
    public function __construct(
        private readonly array $users,
        private readonly array $loginFields = ['email'],
        private readonly string $idField = 'id',
        ?array $validators = null,
    ) {
        $this->validators = $validators ?? [new PasswordCredentialsValidator()];
    }

    public function retrieveById(int|string $identifier): mixed
    {
        foreach ($this->users as $user) {
            $id = UserAccessor::get($user, $this->idField);
            if ($id == $identifier) {
                return $user;
            }
        }

        return null;
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $filters = $this->filterLoginCredentials($credentials);
        if ($filters === []) {
            return null;
        }

        foreach ($this->users as $user) {
            if ($this->matchUser($user, $filters)) {
                return $user;
            }
        }

        return null;
    }

    public function validateCredentials(mixed $user, array $credentials): bool
    {
        $matched = false;

        foreach ($this->validators as $validator) {
            if (!$validator->supports($credentials)) {
                continue;
            }

            $matched = true;
            if (!$validator->validate($user, $credentials)) {
                return false;
            }
        }

        return $matched;
    }

    public function getUserId(mixed $user): int|string|null
    {
        $id = UserAccessor::get($user, $this->idField);

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    private function filterLoginCredentials(array $credentials): array
    {
        $filtered = [];
        foreach ($this->loginFields as $field) {
            if (array_key_exists($field, $credentials)) {
                $filtered[$field] = $credentials[$field];
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function matchUser(mixed $user, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if (UserAccessor::get($user, $key) != $value) {
                return false;
            }
        }

        return true;
    }
}
