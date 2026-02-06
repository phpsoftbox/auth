<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Enrollment\DatabaseEnrollmentCredentialStore;
use PhpSoftBox\Auth\Enrollment\EnrollmentCredentialException;
use PhpSoftBox\Auth\Enrollment\EnrollmentFailureReasonEnum;
use PhpSoftBox\Auth\Enrollment\EnrollmentGrant;
use PhpSoftBox\Auth\Token\DatabaseCredentialStore;
use PhpSoftBox\Auth\Token\IssuedCredential;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function date_default_timezone_get;
use function date_default_timezone_set;

#[CoversClass(DatabaseEnrollmentCredentialStore::class)]
#[CoversMethod(DatabaseEnrollmentCredentialStore::class, 'issue')]
#[CoversMethod(DatabaseEnrollmentCredentialStore::class, 'consume')]
#[CoversMethod(DatabaseEnrollmentCredentialStore::class, 'exchange')]
#[CoversMethod(DatabaseEnrollmentCredentialStore::class, 'revoke')]
final class DatabaseEnrollmentCredentialStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет, что exchange атомарно погашает enrollment и выпускает отдельные credentials разрешённых API.
     *
     * @see DatabaseEnrollmentCredentialStore::issue()
     * @see DatabaseEnrollmentCredentialStore::exchange()
     * @see DatabaseCredentialStore::issue()
     */
    #[Test]
    public function exchangeIssuesCredentialsForAllowedApiAudiences(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-08-31 10:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createEnrollmentTable($manager);
        $this->createTokenTable($manager);

        $enrollment = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');
        $catalog    = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'catalog');
        $warehouse  = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'warehouse');
        $issued     = $enrollment->issue(
            subjectId: 'device-42',
            allowedAudiences: ['catalog', 'warehouse'],
            expiresAt: new DateTimeImmutable('2026-08-31 10:10:00 UTC'),
        );

        /** @var array<string, IssuedCredential> $credentials */
        $credentials = $enrollment->exchange(
            $issued->token,
            static function (EnrollmentGrant $grant) use ($catalog, $warehouse): array {
                self::assertSame(['catalog', 'warehouse'], $grant->allowedAudiences);

                return [
                    'catalog'   => $catalog->issue($grant->subjectId),
                    'warehouse' => $warehouse->issue($grant->subjectId),
                ];
            },
        );

        self::assertNotNull($catalog->findValid($credentials['catalog']->token));
        self::assertNull($warehouse->findValid($credentials['catalog']->token));
        self::assertNotNull($warehouse->findValid($credentials['warehouse']->token));
        self::assertNull($catalog->findValid($credentials['warehouse']->token));
    }

    /**
     * Проверяет, что успешно погашенный enrollment credential нельзя использовать повторно.
     *
     * @see DatabaseEnrollmentCredentialStore::consume()
     */
    #[Test]
    public function consumedCredentialCannotBeUsedTwice(): void
    {
        $manager = $this->connectionManager();
        $this->createEnrollmentTable($manager);

        $store = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');

        $issued = $store->issue('device-42', ['catalog']);

        $store->consume($issued->token);

        try {
            $store->consume($issued->token);
            self::fail('Consumed enrollment credential must not be accepted twice.');
        } catch (EnrollmentCredentialException $exception) {
            self::assertSame(EnrollmentFailureReasonEnum::AlreadyUsed, $exception->reason);
        }
    }

    /**
     * Проверяет, что ошибка callback откатывает и погашение enrollment, и выпущенный API credential.
     *
     * @see DatabaseEnrollmentCredentialStore::exchange()
     * @see DatabaseCredentialStore::issue()
     */
    #[Test]
    public function failedExchangeRollsBackAllCredentialChanges(): void
    {
        $manager = $this->connectionManager();
        $this->createEnrollmentTable($manager);
        $this->createTokenTable($manager);

        $enrollment = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');
        $catalog    = new DatabaseCredentialStore($manager, purpose: 'api_access', audience: 'catalog');
        $issued     = $enrollment->issue('device-42', ['catalog']);
        $access     = null;

        try {
            $enrollment->exchange(
                $issued->token,
                static function (EnrollmentGrant $grant) use ($catalog, &$access): never {
                    $access = $catalog->issue($grant->subjectId);

                    throw new RuntimeException('Simulated exchange failure.');
                },
            );
            self::fail('Failed exchange callback must be propagated.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated exchange failure.', $exception->getMessage());
        }

        self::assertInstanceOf(IssuedCredential::class, $access);
        self::assertNull($catalog->findValid($access->token));
        self::assertSame('device-42', $enrollment->consume($issued->token)->subjectId);
    }

    /**
     * Проверяет, что enrollment credential не принимается другим enrollment API.
     *
     * @see DatabaseEnrollmentCredentialStore::consume()
     */
    #[Test]
    public function credentialIsRejectedForAnotherAudience(): void
    {
        $manager = $this->connectionManager();
        $this->createEnrollmentTable($manager);

        $devices = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');
        $staff   = new DatabaseEnrollmentCredentialStore($manager, audience: 'staff-enrollment');
        $issued  = $devices->issue('device-42', ['catalog']);

        try {
            $staff->consume($issued->token);
            self::fail('Enrollment credential must be bound to its configured audience.');
        } catch (EnrollmentCredentialException $exception) {
            self::assertSame(EnrollmentFailureReasonEnum::Invalid, $exception->reason);
        }
    }

    /**
     * Проверяет отдельную причину отказа для истёкшего enrollment credential.
     *
     * @see DatabaseEnrollmentCredentialStore::consume()
     */
    #[Test]
    public function expiredCredentialReportsExpiredReason(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-08-31 10:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createEnrollmentTable($manager);

        $store = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');

        $issued = $store->issue(
            'device-42',
            ['catalog'],
            new DateTimeImmutable('2026-08-31 09:59:59 UTC'),
        );

        try {
            $store->consume($issued->token);
            self::fail('Expired enrollment credential must be rejected.');
        } catch (EnrollmentCredentialException $exception) {
            self::assertSame(EnrollmentFailureReasonEnum::Expired, $exception->reason);
        }
    }

    /**
     * Проверяет чтение сохранённого UTC-срока независимо от timezone приложения.
     *
     * @see DatabaseEnrollmentCredentialStore::consume()
     */
    #[Test]
    public function utcExpirationIsReadIndependentlyOfApplicationTimezone(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-08-31 10:00:00 UTC'));

        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow');

        try {
            $manager = $this->connectionManager();
            $this->createEnrollmentTable($manager);

            $store = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');

            $issued = $store->issue(
                'device-42',
                ['catalog'],
                new DateTimeImmutable('2026-08-31 10:10:00 UTC'),
            );

            self::assertSame('device-42', $store->consume($issued->token)->subjectId);
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    /**
     * Проверяет, что отозванный enrollment credential возвращает общую invalid-причину.
     *
     * @see DatabaseEnrollmentCredentialStore::revoke()
     * @see DatabaseEnrollmentCredentialStore::consume()
     */
    #[Test]
    public function revokedCredentialReportsInvalidReason(): void
    {
        $manager = $this->connectionManager();
        $this->createEnrollmentTable($manager);

        $store = new DatabaseEnrollmentCredentialStore($manager, audience: 'device-enrollment');

        $issued = $store->issue('device-42', ['catalog']);
        $store->revoke($issued->token);

        try {
            $store->consume($issued->token);
            self::fail('Revoked enrollment credential must be rejected.');
        } catch (EnrollmentCredentialException $exception) {
            self::assertSame(EnrollmentFailureReasonEnum::Invalid, $exception->reason);
        }
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

    private function createEnrollmentTable(ConnectionManager $manager): void
    {
        $manager->connection()->schema()->create('enrollment_credentials', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('subject_id', 64);
            $table->string('audience', 64);
            $table->json('allowed_audiences');
            $table->string('selector', 64);
            $table->string('token_hash', 128);
            $table->datetime('expires_datetime')->nullable();
            $table->datetime('revoked_datetime')->nullable();
            $table->datetime('used_datetime')->nullable();
            $table->datetime('created_datetime');
            $table->string('created_ip', 45)->nullable();
            $table->string('created_user_agent', 512)->nullable();
            $table->string('used_ip', 45)->nullable();
            $table->string('used_user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->unique(['selector']);
            $table->index(['subject_id', 'audience']);
        });
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
