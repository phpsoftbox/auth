<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;

final readonly class DatabaseOwnerResolver implements OwnershipResolverInterface
{
    public function __construct(
        private ConnectionManagerInterface $connections,
        private string $table,
        private string $idColumn = 'id',
        private string $ownerColumn = 'user_id',
        private string $connection = 'default',
    ) {
    }

    public function resolve(
        mixed $routeValue,
        ServerRequestInterface $request,
        OwnershipBinding $binding,
    ): ?OwnershipSubject {
        $id = $this->resolveId($routeValue);
        if ($id === null) {
            return null;
        }

        $conn = $this->connections->read($this->connection);
        $row  = $conn->fetchOne(sprintf(
            'SELECT %s AS resource_id, %s AS owner_id FROM %s WHERE %s = :id LIMIT 1',
            $this->idColumn,
            $this->ownerColumn,
            $conn->table($this->table),
            $this->idColumn,
        ), ['id' => $id]);

        if ($row === null) {
            return null;
        }

        $resourceId = $row['resource_id'] ?? $id;
        $ownerId    = $row['owner_id'] ?? null;
        if (!is_int($ownerId) && !is_string($ownerId)) {
            $ownerId = null;
        }

        return new OwnershipSubject(
            type: $binding->subject ?? $this->table,
            id: is_int($resourceId) || is_string($resourceId) ? $resourceId : $id,
            ownerId: $ownerId,
            routeParam: $binding->routeParam,
            value: $routeValue,
        );
    }

    private function resolveId(mixed $value): int|string|null
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'id')) {
            $id = $value->id();
            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        if (is_object($value) && isset($value->id) && (is_int($value->id) || is_string($value->id))) {
            return $value->id;
        }

        return null;
    }
}
