<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Provider\DatabaseUserProvider;
use PhpSoftBox\Auth\Tests\Fixtures\TestIdentity;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseUserProvider::class)]
final class DatabaseUserProviderTest extends TestCase
{
    /**
     * Проверяет, что identityClass маппится через AutoEntityMapper даже без явного identityResolver.
     */
    #[Test]
    public function identityMapperIsUsedWhenProvided(): void
    {
        $manager = $this->buildConnectionManager();

        $manager->connection()->schema()->create('users', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('marker', 100)->nullable();
        });

        $manager->connection()->execute(
            'INSERT INTO users (id, marker) VALUES (:id, :marker)',
            ['id' => 1, 'marker' => 'hello'],
        );

        $metadata = new AttributeMetadataProvider();

        $mapper = new AutoEntityMapper(
            $metadata,
            new DefaultTypeCasterFactory()->create(),
            new TypeCastOptionsManager(),
        );

        $provider = new DatabaseUserProvider(
            connections: $manager,
            identityClass: TestIdentity::class,
            identityMapper: $mapper,
        );

        $user = $provider->retrieveById(1);

        self::assertNotNull($user);
        $identity = $user->identity();
        self::assertInstanceOf(TestIdentity::class, $identity);
        self::assertSame('hello', $identity->marker);
    }

    private function buildConnectionManager(): ConnectionManager
    {
        $factory = new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ]);

        return new ConnectionManager($factory);
    }
}
