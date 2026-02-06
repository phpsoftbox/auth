<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Subject\DatabaseOwnerResolver;
use PhpSoftBox\Auth\Authorization\Subject\OwnershipBinding;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseOwnerResolver::class)]
final class DatabaseOwnerResolverTest extends TestCase
{
    /**
     * Проверяет, что resolver получает owner id из БД и возвращает lightweight ownership subject.
     */
    #[Test]
    public function resolvesOwnerIdFromDatabase(): void
    {
        $factory = new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ]);

        $connections = new ConnectionManager($factory);

        $conn = $connections->connection();

        $conn->schema()->create('companies', static function (TableBlueprint $table): void {
            $table->id();
            $table->integer('user_id');
        });

        $conn->execute('INSERT INTO companies (id, user_id) VALUES (:id, :user_id)', [
            'id'      => 77,
            'user_id' => 10,
        ]);

        $resolver = new DatabaseOwnerResolver(
            connections: $connections,
            table: 'companies',
            idColumn: 'id',
            ownerColumn: 'user_id',
        );

        $subject = $resolver->resolve(
            routeValue: 77,
            request: new ServerRequest('GET', 'https://example.com/companies/77'),
            binding: new OwnershipBinding('company', 'company', $resolver),
        );

        self::assertNotNull($subject);
        self::assertSame('company', $subject->type);
        self::assertSame(77, (int) $subject->id);
        self::assertSame(10, (int) $subject->ownerId);
        self::assertSame('company', $subject->routeParam);
    }

    /**
     * Проверяет, что отсутствующая запись превращается в null, а не в allow.
     */
    #[Test]
    public function returnsNullWhenResourceIsMissing(): void
    {
        $factory = new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ]);

        $connections = new ConnectionManager($factory);

        $conn = $connections->connection();

        $conn->schema()->create('companies', static function (TableBlueprint $table): void {
            $table->id();
            $table->integer('user_id');
        });

        $resolver = new DatabaseOwnerResolver($connections, 'companies');

        self::assertNull($resolver->resolve(
            routeValue: 404,
            request: new ServerRequest('GET', 'https://example.com/companies/404'),
            binding: new OwnershipBinding('company', 'company', $resolver),
        ));
    }
}
