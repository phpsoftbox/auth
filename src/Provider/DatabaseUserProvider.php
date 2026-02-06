<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use Closure;
use InvalidArgumentException;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;

use function array_key_exists;
use function get_debug_type;
use function implode;
use function sprintf;

final class DatabaseUserProvider implements UserProviderInterface
{
    /** @var list<CredentialsValidatorInterface> */
    private array $validators;
    private readonly Closure $userResolver;

    /**
     * @param list<string> $loginFields
     * @param list<CredentialsValidatorInterface> $validators
     * @param class-string $identityClass
     * @param callable(mixed, array<string, mixed>):UserInterface|null $userResolver
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $identityClass,
        private readonly AutoEntityMapper $identityMapper,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'users',
        private readonly array $loginFields = ['email'],
        private readonly string $idField = 'id',
        array $validators = [],
        ?callable $userResolver = null,
    ) {
        foreach ($validators as $validator) {
            if (!$validator instanceof CredentialsValidatorInterface) {
                throw new InvalidArgumentException('Credentials validator must implement CredentialsValidatorInterface.');
            }
        }

        $this->validators   = $validators;
        $this->userResolver = $userResolver === null
            ? static fn (mixed $identity, array $_row): mixed => $identity
            : Closure::fromCallable($userResolver);
    }

    public function retrieveById(int|string $identifier): ?UserInterface
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            $conn->table($this->table),
            $this->idField,
        );

        $row = $conn->fetchOne($sql, ['id' => $identifier]);

        return $row === null ? null : $this->resolveUser($row);
    }

    public function retrieveByCredentials(array $credentials): ?UserInterface
    {
        $filters = $this->filterLoginCredentials($credentials);
        if ($filters === []) {
            return null;
        }

        $conn       = $this->connections->read($this->connectionName);
        $conditions = [];
        $params     = [];
        foreach ($filters as $field => $value) {
            $param          = 'p_' . $field;
            $conditions[]   = $field . ' = :' . $param;
            $params[$param] = $value;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s LIMIT 1',
            $conn->table($this->table),
            implode(' AND ', $conditions),
        );

        $row = $conn->fetchOne($sql, $params);

        return $row === null ? null : $this->resolveUser($row);
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
     * @param array<string, mixed> $row
     */
    private function resolveUser(array $row): UserInterface
    {
        $identity = $this->identityMapper->hydrate($this->identityClass, $row);
        $user     = ($this->userResolver)($identity, $row);
        if (!$user instanceof UserInterface) {
            throw new InvalidArgumentException('Resolved user must implement UserInterface, got ' . get_debug_type($user) . '.');
        }

        return $user;
    }
}
