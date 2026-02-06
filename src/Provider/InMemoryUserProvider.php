<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use Closure;
use InvalidArgumentException;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;

use function is_int;
use function is_string;

final class InMemoryUserProvider implements UserProviderInterface
{
    /**
     * @var list<UserInterface>
     */
    private readonly array $users;

    /**
     * @var list<CredentialsValidatorInterface>
     */
    private readonly array $validators;
    private readonly ?Closure $credentialsMatcher;

    /**
     * @param list<UserInterface> $users
     * @param callable(UserInterface, array<string, mixed>):bool|null $credentialsMatcher
     * @param list<CredentialsValidatorInterface> $validators
     */
    public function __construct(
        array $users,
        ?callable $credentialsMatcher = null,
        array $validators = [],
    ) {
        foreach ($users as $user) {
            if (!$user instanceof UserInterface) {
                throw new InvalidArgumentException('InMemoryUserProvider accepts only UserInterface instances.');
            }
        }

        foreach ($validators as $validator) {
            if (!$validator instanceof CredentialsValidatorInterface) {
                throw new InvalidArgumentException('Credentials validator must implement CredentialsValidatorInterface.');
            }
        }

        $this->users              = $users;
        $this->credentialsMatcher = $credentialsMatcher === null ? null : Closure::fromCallable($credentialsMatcher);
        $this->validators         = $validators;
    }

    public function retrieveById(int|string $identifier): ?UserInterface
    {
        foreach ($this->users as $user) {
            $id = $user->id();
            if ((is_int($id) || is_string($id)) && (string) $id === (string) $identifier) {
                return $user;
            }
        }

        return null;
    }

    public function retrieveByCredentials(array $credentials): ?UserInterface
    {
        if ($this->credentialsMatcher === null) {
            return null;
        }

        foreach ($this->users as $user) {
            if (($this->credentialsMatcher)($user, $credentials)) {
                return $user;
            }
        }

        return null;
    }

    public function validateCredentials(UserInterface $user, array $credentials): bool
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
}
