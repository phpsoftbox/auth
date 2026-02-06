<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Auth\Credentials\CredentialsValidatorInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\Support\UserAccessor;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

use function array_key_exists;
use function implode;
use function is_int;
use function is_string;
use function sprintf;

final class DatabaseUserProvider implements UserProviderInterface
{
    /** @var list<CredentialsValidatorInterface> */
    private array $validators;

    /**
     * @param list<string> $loginFields
     * @param list<CredentialsValidatorInterface>|null $validators
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $table = 'users',
        private readonly array $loginFields = ['email'],
        private readonly string $idField = 'id',
        ?array $validators = null,
    ) {
        $this->validators = $validators ?? [new PasswordCredentialsValidator()];
    }

    public function retrieveById(int|string $identifier): mixed
    {
        $conn = $this->connections->read($this->connectionName);
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = :id LIMIT 1',
            $conn->table($this->table),
            $this->idField,
        );

        return $conn->fetchOne($sql, ['id' => $identifier]);
    }

    public function retrieveByCredentials(array $credentials): mixed
    {
        $filters = $this->filterLoginCredentials($credentials);
        if ($filters === []) {
            return null;
        }

        $conn = $this->connections->read($this->connectionName);
        $conditions = [];
        $params = [];
        foreach ($filters as $field => $value) {
            $param = 'p_' . $field;
            $conditions[] = $field . ' = :' . $param;
            $params[$param] = $value;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s LIMIT 1',
            $conn->table($this->table),
            implode(' AND ', $conditions),
        );

        return $conn->fetchOne($sql, $params);
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
}
