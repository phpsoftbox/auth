<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Remember\DatabaseRememberTokenStore;
use PhpSoftBox\Auth\Remember\MultiGuardRememberService;
use PhpSoftBox\Auth\Remember\RememberCookieConfig;
use PhpSoftBox\Auth\Remember\RememberCookieManager;
use PhpSoftBox\Auth\Remember\RememberGuardConfig;
use PhpSoftBox\Auth\Remember\RememberTokenExtractor;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\ArraySessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function password_hash;

use const PASSWORD_DEFAULT;

#[CoversClass(MultiGuardRememberService::class)]
#[CoversClass(RememberGuardConfig::class)]
final class MultiGuardRememberServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    #[Test]
    public function issueQueuesRememberCookieForConfiguredGuard(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $queue = new CookieQueue();

        $config = $this->guardConfig($manager, $queue, 'web_remember');

        $service = new MultiGuardRememberService(true, 30, [
            'web' => $config,
        ]);

        $service->issue('web', 10, new ServerRequest('GET', 'https://example.test/'));

        $cookies = $queue->flush();

        self::assertCount(1, $cookies);
        self::assertSame('web_remember', $cookies[0]->name());
        self::assertNotSame('', $cookies[0]->value());
    }

    #[Test]
    public function restoreLogsUserIntoConfiguredGuard(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $queue = new CookieQueue();

        $config = $this->guardConfig($manager, $queue, 'tenant_remember');
        $issued = $config->store->issue(20, new DateTimeImmutable('2026-02-01 00:00:00 UTC'));

        $service = new MultiGuardRememberService(true, 30, [
            'tenant' => $config,
        ]);

        $restored = $service->restore(
            'tenant',
            new ServerRequest('GET', 'https://tenant.example.test/', cookieParams: ['tenant_remember' => $issued->token]),
        );

        self::assertTrue($restored);
        self::assertSame(20, $config->guard->user(new ServerRequest('GET', 'https://tenant.example.test/'))?->id());
    }

    #[Test]
    public function forgetRevokesRememberTokenAndQueuesForgetCookie(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $queue = new CookieQueue();

        $config = $this->guardConfig($manager, $queue, 'remember_token');
        $issued = $config->store->issue(10, new DateTimeImmutable('2026-02-01 00:00:00 UTC'));

        $service = new MultiGuardRememberService(true, 30, [
            'web' => $config,
        ]);

        $service->forget(
            'web',
            new ServerRequest('GET', 'https://example.test/', cookieParams: ['remember_token' => $issued->token]),
        );

        self::assertNull($config->store->findValid($issued->token));

        $cookies = $queue->flush();

        self::assertSame('remember_token', $cookies[0]->name() ?? null);
        self::assertSame('', $cookies[0]->value() ?? null);
    }

    #[Test]
    public function unknownGuardThrows(): void
    {
        $service = new MultiGuardRememberService(true, 30, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Remember guard is not configured');

        $service->issue('missing', 10, new ServerRequest('GET', 'https://example.test/'));
    }

    private function guardConfig(ConnectionManager $manager, CookieQueue $queue, string $cookieName): RememberGuardConfig
    {
        $users = new InMemoryUserProvider(
            users: [
                new AuthTestUser(id: 10, email: 'first@example.test', passwordHash: password_hash('secret', PASSWORD_DEFAULT)),
                new AuthTestUser(id: 20, email: 'second@example.test', passwordHash: password_hash('secret', PASSWORD_DEFAULT)),
            ],
            credentialsMatcher: static fn (UserInterface $user, array $credentials): bool => $user instanceof AuthTestUser
                && $user->email === ($credentials['email'] ?? null),
        );

        return new RememberGuardConfig(
            store: new DatabaseRememberTokenStore($manager, touchThrottleSeconds: 0),
            cookies: new RememberCookieManager($queue, new RememberCookieConfig(name: $cookieName)),
            extractor: new RememberTokenExtractor($cookieName),
            users: $users,
            guard: new SessionGuard(new Session(new ArraySessionStore()), $users),
        );
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
