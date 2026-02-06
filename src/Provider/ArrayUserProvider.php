<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use Closure;
use InvalidArgumentException;
use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\User\AuthUser;

use function array_key_exists;
use function array_map;
use function is_array;
use function is_int;
use function is_string;

class ArrayUserProvider implements UserProviderInterface
{
    /** @var list<CredentialsValidatorInterface> */
    private array $validators;

    /**
     * @param list<array<string, mixed>|UserDataInterface> $users
     * @param list<string> $loginFields
     * @param list<CredentialsValidatorInterface>|null $validators
     * @param callable(array<string, mixed>):mixed|null $identityResolver
     */
    public function __construct(
        array $users,
        private readonly array $loginFields = ['email'],
        private readonly string $idField = 'id',
        ?array $validators = null,
        private readonly ?Closure $identityResolver = null,
    ) {
        $this->users = array_map(function (mixed $user): mixed {
            if ($user instanceof UserDataInterface) {
                return $user;
            }

            if (is_array($user)) {
                return new AuthUser($user, $this->idField, $this->resolveIdentity($user));
            }

            throw new InvalidArgumentException('User must implement UserDataInterface or be an array.');
        }, $users);

        $this->validators = $validators ?? [new PasswordCredentialsValidator()];
    }

    /**
     * @var list<mixed>
     */
    private readonly array $users;

    public function retrieveById(int|string $identifier): mixed
    {
        foreach ($this->users as $user) {
            $id = $this->resolveUserId($user);
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
        $id = $this->resolveUserId($user);

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
            $actual = null;
            if ($user instanceof UserDataInterface) {
                $actual = $user->get($key);
            } elseif (is_array($user)) {
                $actual = $user[$key] ?? null;
            }

            if ($actual != $value) {
                return false;
            }
        }

        return true;
    }

    private function resolveUserId(mixed $user): int|string|null
    {
        if ($user instanceof UserIdentityInterface) {
            return $user->getId();
        }

        if ($user instanceof UserDataInterface) {
            return $user->get($this->idField);
        }

        if (is_array($user)) {
            return $user[$this->idField] ?? null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveIdentity(array $row): mixed
    {
        if ($this->identityResolver === null) {
            return $row;
        }

        return ($this->identityResolver)($row);
    }
}
