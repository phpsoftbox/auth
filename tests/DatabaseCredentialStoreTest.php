<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Provider\DatabaseTokenProvider;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Auth\Token\DatabaseCredentialStore;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function str_contains;

#[CoversClass(DatabaseCredentialStore::class)]
#[CoversClass(DatabaseTokenProvider::class)]
#[CoversMethod(DatabaseCredentialStore::class, 'issue')]
#[CoversMethod(DatabaseCredentialStore::class, 'findValid')]
#[CoversMethod(DatabaseCredentialStore::class, 'revoke')]
#[CoversMethod(DatabaseCredentialStore::class, 'revokeAllForSubject')]
final class DatabaseCredentialStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет выдачу hash-token и lookup пользователя без хранения raw-token в БД.
     *
     * @see DatabaseCredentialStore::issue()
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function issueStoresHashAndProviderResolvesUser(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseCredentialStore($manager, touchThrottleSeconds: 0);

        $request = new ServerRequest(
            'GET',
            'https://admin.example.test',
            ['User-Agent' => 'Browser'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );

        $issued = $store->issue(
            subjectId: 10,
            expiresAt: new DateTimeImmutable('2026-01-02 00:00:00 UTC'),
            metadata: ['device' => 'web'],
            request: $request,
        );

        $row = $manager->connection()->fetchOne('SELECT * FROM user_tokens WHERE selector = :selector', [
            'selector' => $issued->selector,
        ]);

        self::assertNotNull($row);
        self::assertSame('10', (string) $row['user_id']);
        self::assertSame(DatabaseCredentialStore::PURPOSE_USER_BEARER, $row['token_type']);
        self::assertNotSame($issued->token, $row['token_hash']);
        self::assertFalse(str_contains((string) $row['token_hash'], '.'));
        self::assertSame('127.0.0.1', $row['created_ip']);
        self::assertSame('Browser', $row['created_user_agent']);

        $users = new InMemoryUserProvider([
            new AuthTestUser(id: 10, email: 'test@example.test'),
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
     *
     * @see DatabaseCredentialStore::revoke()
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function revokedTokenIsRejected(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseCredentialStore($manager);

        $issued = $store->issue(10, new DateTimeImmutable('2026-01-02 00:00:00 UTC'));

        self::assertNotNull($store->findValid($issued->token));

        $store->revoke($issued->token);

        self::assertNull($store->findValid($issued->token));
    }

    /**
     * Проверяет, что истёкший token не проходит.
     *
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function expiredTokenIsRejected(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-02 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseCredentialStore($manager);

        $issued = $store->issue(10, new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        self::assertNull($store->findValid($issued->token));
    }

    /**
     * Проверяет чтение сохранённого UTC-срока независимо от timezone приложения.
     *
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function utcExpirationIsReadIndependentlyOfApplicationTimezone(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 10:00:00 UTC'));

        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow');

        try {
            $manager = $this->connectionManager();
            $this->createTokenTable($manager);

            $store = new DatabaseCredentialStore($manager);

            $issued = $store->issue(10, new DateTimeImmutable('2026-01-01 10:10:00 UTC'));

            self::assertNotNull($store->findValid($issued->token));
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    /**
     * Проверяет поддержку строковых идентификаторов subject в generic credential storage.
     *
     * @see DatabaseCredentialStore::issue()
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function supportsStringSubjectIdentifiers(): void
    {
        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $store = new DatabaseCredentialStore($manager, purpose: 'node_bearer');

        $issued = $store->issue('node-eu-42');
        $record = $store->findValid($issued->token);

        self::assertSame('node-eu-42', $issued->subjectId);
        self::assertSame('node-eu-42', $record?->subjectId);
        self::assertSame('node_bearer', $record?->purpose);
    }

    /**
     * Проверяет, что purpose изолирует credentials разных auth flow в общей таблице.
     *
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function purposeSeparatesCredentialsUsingTheSameTable(): void
    {
        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $enrollment = new DatabaseCredentialStore($manager, purpose: 'node_enrollment');
        $bearer     = new DatabaseCredentialStore($manager, purpose: 'node_bearer');
        $issued     = $enrollment->issue(42);

        self::assertNotNull($enrollment->findValid($issued->token));
        self::assertNull($bearer->findValid($issued->token));
    }

    /**
     * Проверяет, что credential принимается только API с совпадающим audience.
     *
     * @see DatabaseCredentialStore::issue()
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function audienceSeparatesCredentialsUsingTheSamePurpose(): void
    {
        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $catalog   = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'catalog');
        $warehouse = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'warehouse');
        $unscoped  = new DatabaseCredentialStore($manager, purpose: 'api_access');
        $issued    = $catalog->issue(42);
        $record    = $catalog->findValid($issued->token);
        $legacy    = $unscoped->issue(42);

        self::assertSame('catalog', $issued->audience);
        self::assertSame('catalog', $record?->audience);
        self::assertNull($warehouse->findValid($issued->token));
        self::assertNull($unscoped->findValid($issued->token));
        self::assertNull($catalog->findValid($legacy->token));
    }

    /**
     * Проверяет, что массовый отзыв subject не затрагивает credential другого API.
     *
     * @see DatabaseCredentialStore::revokeAllForSubject()
     * @see DatabaseCredentialStore::findValid()
     */
    #[Test]
    public function revokeAllForSubjectIsLimitedToConfiguredAudience(): void
    {
        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $catalog        = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'catalog');
        $warehouse      = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'warehouse');
        $catalogToken   = $catalog->issue(42);
        $warehouseToken = $warehouse->issue(42);

        self::assertSame(1, $catalog->revokeAllForSubject(42));
        self::assertNull($catalog->findValid($catalogToken->token));
        self::assertNotNull($warehouse->findValid($warehouseToken->token));
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
            $table->string('audience', 64)->nullable();
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
            $table->index(['user_id', 'token_type', 'audience']);
        });
    }
}
