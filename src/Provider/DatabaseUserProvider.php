<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use Closure;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\User\AuthUser;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;

use function array_key_exists;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

final class DatabaseUserProvider implements UserProviderInterface
{
    /** @var list<CredentialsValidatorInterface> */
    private array $validators;
    private readonly Closure $identityResolver;

    /**
     * @param list<string> $loginFields
     * @param list<CredentialsValidatorInterface>|null $validators
     * @param class-string $identityClass
     * @param callable(mixed, array<string, mixed>):mixed|null $identityResolver
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $identityClass,
        private readonly AutoEntityMapper $identityMapper,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'users',
        private readonly array $loginFields = ['email'],
        private readonly string $idField = 'id',
        ?array $validators = null,
        ?Closure $identityResolver = null,
    ) {
        $this->validators       = $validators ?? [new PasswordCredentialsValidator()];
        $this->identityResolver = $identityResolver ?? static fn (mixed $identity, array $_row): mixed => $identity;
    }

    public function retrieveById(int|string $identifier): mixed
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            $conn->table($this->table),
            $this->idField,
        );

        $row = $conn->fetchOne($sql, ['id' => $identifier]);

        return is_array($row) ? new AuthUser($row, $this->idField, $this->resolveIdentity($row)) : null;
    }

    public function retrieveByCredentials(array $credentials): mixed
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

        return is_array($row) ? new AuthUser($row, $this->idField, $this->resolveIdentity($row)) : null;
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
        if ($user instanceof UserInterface) {
            return $user->id();
        }

        $id = $user;

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
     * @param array<string, mixed> $row
     */
    private function resolveIdentity(array $row): mixed
    {
        $identity = $this->identityMapper->hydrate($this->identityClass, $row);

        return ($this->identityResolver)($identity, $row);
    }
}
