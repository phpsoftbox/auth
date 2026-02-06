<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\Database\DatabasePermissionStore;
use PhpSoftBox\Auth\Authorization\Store\Database\DatabaseRoleStore;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabasePermissionStore::class)]
#[CoversClass(DatabaseRoleStore::class)]
final class DatabaseStoresTest extends TestCase
{
    /**
     * Проверяет, что PermissionStore возвращает идентификатор вставленной записи.
     */
    #[Test]
    public function permissionStoreReturnsInsertedId(): void
    {
        $manager = $this->buildConnectionManager();

        $manager->connection()->schema()->create('permissions', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('label', 255)->nullable();
        });

        $store = new DatabasePermissionStore($manager);

        $id = $store->create('admin.access', 'Админка');

        self::assertSame(1, $id);
        self::assertSame(['admin.access' => 1], $store->listIdsByName());

        $store->deleteByIds([$id]);
        self::assertSame([], $store->listIdsByName());
    }

    /**
     * Проверяет, что RoleStore возвращает идентификатор вставленной записи.
     */
    #[Test]
    public function roleStoreReturnsInsertedId(): void
    {
        $manager = $this->buildConnectionManager();

        $manager->connection()->schema()->create('roles', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('label', 255)->nullable();
            $table->boolean('admin_access')->default(false);
        });

        $store = new DatabaseRoleStore($manager);

        $id = $store->create('admin', 'Администратор', true);

        self::assertSame(1, $id);
        self::assertSame(['admin' => 1], $store->listIdsByName());

        $store->deleteByIds([$id]);
        self::assertSame([], $store->listIdsByName());
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
