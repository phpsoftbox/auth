<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\DatabasePermissionChecker;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RoleDefinitionProviderInterface;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;

use function count;
use function stripos;

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
     * Проверяет, что флаг admin_access сам по себе не даёт permission.
     */
    #[Test]
    public function deniesPermissionsWhenOnlyAdminAccessFlagExists(): void
    {
        $checker = $this->buildChecker();

        $user = new IdUser(12);

        self::assertFalse($checker->can($user, 'admin.access'));
        self::assertFalse($checker->can($user, 'reports.view'));
    }

    /**
     * Проверяет, что allowAll из RoleDefinition даёт полный доступ.
     */
    #[Test]
    public function allowsUnknownPermissionForAllowAllRoleDefinition(): void
    {
        $checker = $this->buildChecker(new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::admin()->allowAll(),
            ],
        ));

        $user = new IdUser(12);

        self::assertTrue($checker->can($user, 'unknown.permission'));
        self::assertTrue($checker->can($user, 'admin.access'));
        self::assertTrue($checker->can($user, 'reports.view'));
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

    /**
     * Проверяет, что can() работает для строкового user id.
     */
    #[Test]
    public function allowsRolePermissionsForStringUserId(): void
    {
        $checker = $this->buildChecker();

        $user = new IdUser('user-11');

        self::assertTrue($checker->can($user, 'reports.view'));
    }

    /**
     * Проверяет, что повторный can() для того же пользователя работает по cached grant snapshot.
     */
    #[Test]
    public function repeatedCanUsesCachedUserGrantSnapshot(): void
    {
        $logger  = $this->queryLogger();
        $checker = $this->buildChecker(logger: $logger);
        $user    = new IdUser(11);

        $logger->clear();

        self::assertTrue($checker->can($user, 'reports.view'));
        $queriesAfterFirstCheck = $logger->count();

        self::assertTrue($checker->can($user, 'reports.view'));
        self::assertSame($queriesAfterFirstCheck, $logger->count());
        self::assertSame(1, $logger->countSqlContaining('FROM user_roles ur JOIN roles r'));
        self::assertSame(0, $logger->countSqlContaining('SELECT 1 FROM user_roles'));
    }

    /**
     * Проверяет, что несколько разных permission для allow-all роли не повторяют allow-all check.
     */
    #[Test]
    public function multiplePermissionsUseOneAllowAllRoleLookup(): void
    {
        $logger  = $this->queryLogger();
        $checker = $this->buildChecker(
            definitions: new ArrayRoleDefinitionProvider(
                roles: [
                    RoleDefinition::admin()->allowAll(),
                ],
            ),
            logger: $logger,
        );
        $user = new IdUser(12);

        $logger->clear();

        self::assertTrue($checker->can($user, 'unknown.permission'));
        self::assertTrue($checker->can($user, 'admin.access'));
        self::assertTrue($checker->can($user, 'reports.view'));

        self::assertSame(1, $logger->countSqlContaining('FROM user_roles ur JOIN roles r'));
        self::assertSame(0, $logger->countSqlContaining('FROM user_permissions up'));
        self::assertSame(0, $logger->countSqlContaining('FROM role_permissions rp'));
    }

    /**
     * Проверяет, что reset() сбрасывает cached snapshot для long-running runtime.
     */
    #[Test]
    public function resetClearsCachedUserGrantSnapshots(): void
    {
        $logger  = $this->queryLogger();
        $checker = $this->buildChecker(logger: $logger);
        $user    = new IdUser(11);

        $logger->clear();
        self::assertTrue($checker->can($user, 'reports.view'));
        self::assertSame(1, $logger->countSqlContaining('FROM user_roles ur JOIN roles r'));

        $logger->clear();
        $checker->reset();

        self::assertTrue($checker->can($user, 'reports.view'));
        self::assertSame(1, $logger->countSqlContaining('FROM user_roles ur JOIN roles r'));
    }

    private function buildChecker(
        ?RoleDefinitionProviderInterface $definitions = null,
        ?LoggerInterface $logger = null,
    ): DatabasePermissionChecker {
        $factory = new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ], logger: $logger);

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
        $conn->execute('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, 1)', ['user_id' => 'user-11']);
        $conn->execute('INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 2)');

        return new DatabasePermissionChecker($manager, roleDefinitions: $definitions);
    }

    private function queryLogger(): object
    {
        return new class () extends AbstractLogger {
            /**
             * @var list<array{level: mixed, message: string, context: array<string, mixed>}>
             */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level'   => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }

            public function clear(): void
            {
                $this->records = [];
            }

            public function count(): int
            {
                return count($this->records);
            }

            public function countSqlContaining(string $needle): int
            {
                $count = 0;
                foreach ($this->records as $record) {
                    $sql = (string) ($record['context']['sql'] ?? '');
                    if ($sql !== '' && stripos($sql, $needle) !== false) {
                        $count++;
                    }
                }

                return $count;
            }
        };
    }
}
