<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Remember\DatabaseRememberTokenStore;
use PhpSoftBox\Auth\Token\DatabaseTokenStore;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

#[CoversClass(DatabaseRememberTokenStore::class)]
#[CoversClass(DatabaseTokenStore::class)]
final class DatabaseRememberTokenStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет, что remember-token хранится в user_tokens с отдельным типом и raw-token не попадает в БД.
     */
    #[Test]
    public function issueStoresHashInUserTokensTableWithRememberType(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createRememberTokenTable($manager);

        $store = new DatabaseRememberTokenStore($manager, touchThrottleSeconds: 0);

        $issued = $store->issue(
            userId: 10,
            expiresAt: new DateTimeImmutable('2026-02-01 00:00:00 UTC'),
            metadata: ['device' => 'browser'],
        );

        $row = $manager->connection()->fetchOne('SELECT * FROM user_tokens WHERE selector = :selector', [
            'selector' => $issued->selector,
        ]);

        self::assertNotNull($row);
        self::assertSame('10', (string) $row['user_id']);
        self::assertSame(DatabaseTokenStore::TYPE_REMEMBER, $row['token_type']);
        self::assertNotSame($issued->token, $row['token_hash']);
        self::assertFalse(str_contains((string) $row['token_hash'], '.'));

        self::assertNull(new DatabaseTokenStore($manager)->findValid($issued->token));

        $record = $store->findValid($issued->token);

        self::assertNotNull($record);
        self::assertSame('10', (string) $record->userId);
    }

    /**
     * Проверяет отзыв remember-token.
     */
    #[Test]
    public function revokedRememberTokenIsRejected(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createRememberTokenTable($manager);

        $store = new DatabaseRememberTokenStore($manager);

        $issued = $store->issue(10, new DateTimeImmutable('2026-02-01 00:00:00 UTC'));

        self::assertNotNull($store->findValid($issued->token));

        $store->revoke($issued->token);

        self::assertNull($store->findValid($issued->token));
    }

    private function connectionManager(): ConnectionManager
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

    private function createRememberTokenTable(ConnectionManager $manager): void
    {
        $manager->connection()->schema()->create('user_tokens', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('user_id', 64);
            $table->string('token_type', 32);
            $table->string('selector', 64);
            $table->string('token_hash', 128);
            $table->datetime('expires_datetime')->nullable();
            $table->datetime('revoked_datetime')->nullable();
            $table->datetime('last_used_datetime')->nullable();
            $table->datetime('created_datetime');
            $table->string('created_ip', 45)->nullable();
            $table->string('created_user_agent', 512)->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->string('last_used_user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->unique(['selector']);
            $table->index(['user_id', 'token_type']);
        });
    }
}
