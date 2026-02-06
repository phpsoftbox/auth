<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\DatabasePermissionChecker;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabasePermissionChecker::class)]
final class DatabasePermissionCheckerTest extends TestCase
{
    /**
     * Проверяет, что can() учитывает прямые permission пользователя.
     */
    #[Test]
    public function allowsDirectUserPermission(): void
    {
        $checker = $this->buildChecker();

        $user = new IdUser(10);

        self::assertTrue($checker->can($user, 'admin.access'));
    }

    /**
     * Проверяет, что can() учитывает permission через роли.
     */
    #[Test]
    public function allowsRolePermissions(): void
    {
        $checker = $this->buildChecker();

        $user = new IdUser(11);

        self::assertTrue($checker->can($user, 'reports.view'));
    }

    /**
     * Проверяет, что admin-доступ даётся через флаг роли.
     */
    #[Test]
    public function allowsAdminAccessViaRoleFlag(): void
    {
        $checker = $this->buildChecker();

        $user = new IdUser(12);

        self::assertTrue($checker->can($user, 'admin.access'));
    }

    /**
     * Проверяет, что can() возвращает false, если разрешение не найдено.
     */
    #[Test]
    public function deniesUnknownPermission(): void
    {
        $checker = $this->buildChecker();

        $user = new IdUser(12);

        self::assertFalse($checker->can($user, 'unknown.permission'));
    }

    private function buildChecker(): DatabasePermissionChecker
    {
        $factory = new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ]);

        $manager = new ConnectionManager($factory);

        $conn = $manager->connection();

        $conn->schema()->create('users', static function (TableBlueprint $table): void {
            $table->id();
        });
        $conn->schema()->create('roles', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->boolean('admin_access')->default(false);
        });
        $conn->schema()->create('permissions', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('name', 150);
        });
        $conn->schema()->create('user_roles', static function (TableBlueprint $table): void {
            $table->integer('user_id');
            $table->integer('role_id');
        });
        $conn->schema()->create('role_permissions', static function (TableBlueprint $table): void {
            $table->integer('role_id');
            $table->integer('permission_id');
        });
        $conn->schema()->create('user_permissions', static function (TableBlueprint $table): void {
            $table->integer('user_id');
            $table->integer('permission_id');
        });

        $conn->execute('INSERT INTO roles (id, name, admin_access) VALUES (1, :name, 0)', ['name' => 'support']);
        $conn->execute('INSERT INTO roles (id, name, admin_access) VALUES (2, :name, 1)', ['name' => 'admin']);

        $conn->execute('INSERT INTO permissions (id, name) VALUES (1, :name)', ['name' => 'admin.access']);
        $conn->execute('INSERT INTO permissions (id, name) VALUES (2, :name)', ['name' => 'reports.view']);

        $conn->execute('INSERT INTO user_permissions (user_id, permission_id) VALUES (10, 1)');
        $conn->execute('INSERT INTO user_roles (user_id, role_id) VALUES (11, 1)');
        $conn->execute('INSERT INTO user_roles (user_id, role_id) VALUES (12, 2)');
        $conn->execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 2)');

        return new DatabasePermissionChecker($manager);
    }
}

final class IdUser implements UserIdentityInterface
{
    public function __construct(
        private int $id,
    ) {
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }
}
