<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

use function is_int;
use function is_string;
use function sprintf;

final readonly class DatabaseTokenProvider implements TokenProviderInterface
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private string $connectionName = 'default',
        private string $table = 'user_tokens',
        private string $tokenColumn = 'token',
        private string $userIdColumn = 'user_id',
    ) {
    }

    public function retrieveUserIdByToken(string $token): int|string|null
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT %s FROM %s WHERE %s = :token LIMIT 1',
            $this->userIdColumn,
            $conn->table($this->table),
            $this->tokenColumn,
        );

        $row = $conn->fetchOne($sql, ['token' => $token]);
        if ($row === null) {
            return null;
        }

        $value = $row[$this->userIdColumn] ?? null;

        return is_int($value) || is_string($value) ? $value : null;
    }
}
