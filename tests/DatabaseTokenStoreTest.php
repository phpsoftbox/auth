<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Auth\Provider\DatabaseTokenProvider;
use PhpSoftBox\Auth\Token\DatabaseTokenStore;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function password_hash;
use function str_contains;

use const PASSWORD_DEFAULT;

#[CoversClass(DatabaseTokenStore::class)]
#[CoversClass(DatabaseTokenProvider::class)]
final class DatabaseTokenStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет выдачу hash-token и lookup пользователя без хранения raw-token в БД.
     */
    #[Test]
    public function issueStoresHashAndProviderResolvesUser(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseTokenStore($manager, touchThrottleSeconds: 0);

        $request = new ServerRequest(
            'GET',
            'https://admin.example.test',
            ['User-Agent' => 'Browser'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $issued = $store->issue(
            userId: 10,
            expiresAt: new DateTimeImmutable('2026-01-02 00:00:00 UTC'),
            metadata: ['device' => 'web'],
            request: $request,
        );

        $row = $manager->connection()->fetchOne('SELECT * FROM user_tokens WHERE selector = :selector', [
            'selector' => $issued->selector,
        ]);

        self::assertNotNull($row);
        self::assertSame('10', (string) $row['user_id']);
        self::assertSame(DatabaseTokenStore::TYPE_BEARER, $row['token_type']);
        self::assertNotSame($issued->token, $row['token_hash']);
        self::assertFalse(str_contains((string) $row['token_hash'], '.'));
        self::assertSame('127.0.0.1', $row['created_ip']);
        self::assertSame('Browser', $row['created_user_agent']);

        $users = new ArrayUserProvider([
            ['id' => 10, 'email' => 'test@example.test', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $provider = new DatabaseTokenProvider($store, $users);

        $user = $provider->retrieveUserByTokenForRequest($issued->token, $request);

        self::assertInstanceOf(UserInterface::class, $user);
        self::assertSame(10, $user->id());

        $row = $manager->connection()->fetchOne('SELECT * FROM user_tokens WHERE selector = :selector', [
            'selector' => $issued->selector,
        ]);

        self::assertSame('2026-01-01 00:00:00', $row['last_used_datetime']);
        self::assertSame('127.0.0.1', $row['last_used_ip']);
    }

    /**
     * Проверяет, что revoked token больше не проходит.
     */
    #[Test]
    public function revokedTokenIsRejected(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseTokenStore($manager);

        $issued = $store->issue(10, new DateTimeImmutable('2026-01-02 00:00:00 UTC'));

        self::assertNotNull($store->findValid($issued->token));

        $store->revoke($issued->token);

        self::assertNull($store->findValid($issued->token));
    }

    /**
     * Проверяет, что истёкший token не проходит.
     */
    #[Test]
    public function expiredTokenIsRejected(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-02 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseTokenStore($manager);

        $issued = $store->issue(10, new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

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

    private function createTokenTable(ConnectionManager $manager): void
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
