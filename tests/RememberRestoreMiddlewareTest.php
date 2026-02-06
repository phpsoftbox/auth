<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Remember\DatabaseRememberTokenStore;
use PhpSoftBox\Auth\Remember\RememberCookieManager;
use PhpSoftBox\Auth\Remember\RememberMismatchPolicy;
use PhpSoftBox\Auth\Remember\RememberRestoreMiddleware;
use PhpSoftBox\Auth\Remember\RememberTokenExtractor;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Auth\Tests\Support\CapturingHandler;
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

#[CoversClass(RememberRestoreMiddleware::class)]
#[CoversClass(RememberMismatchPolicy::class)]
final class RememberRestoreMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Clock::reset();
    }

    /**
     * Проверяет, что remember-token восстанавливает session, если session отсутствует.
     */
    #[Test]
    public function restoresSessionFromRememberToken(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $users = $this->users();
        $guard = new SessionGuard(new Session(new ArraySessionStore()), $users);
        $store = new DatabaseRememberTokenStore($manager, touchThrottleSeconds: 0);

        $issued = $store->issue(10, new DateTimeImmutable('2026-02-01 00:00:00 UTC'));

        $handler = new CapturingHandler();

        $this->middleware($guard, $users, $store)->process(
            new ServerRequest('GET', 'https://example.test/', cookieParams: ['remember_token' => $issued->token]),
            $handler,
        );

        self::assertSame(10, $guard->user(new ServerRequest('GET', 'https://example.test/'))?->id());
        self::assertSame(10, $handler->request?->getAttribute('user_id'));
    }

    /**
     * Проверяет, что mismatch session user и remember-token считается подозрительным.
     */
    #[Test]
    public function mismatchRevokesRememberTokenAndForgetsCookie(): void
    {
        Clock::freeze(new DateTimeImmutable('2026-01-01 00:00:00 UTC'));

        $manager = $this->connectionManager();
        $this->createTokenTable($manager);

        $users   = $this->users();
        $session = new Session(new ArraySessionStore());

        $guard = new SessionGuard($session, $users);

        $guard->login($users->retrieveById(10));

        $store = new DatabaseRememberTokenStore($manager, touchThrottleSeconds: 0);

        $issued = $store->issue(20, new DateTimeImmutable('2026-02-01 00:00:00 UTC'));
        $queue  = new CookieQueue();

        $this->middleware($guard, $users, $store, $queue)->process(
            new ServerRequest('GET', 'https://example.test/', cookieParams: ['remember_token' => $issued->token]),
            new CapturingHandler(),
        );

        self::assertNull($store->findValid($issued->token));
        self::assertSame('remember_token=', $this->cookieNameAndValue($queue));
        self::assertSame(10, $guard->user(new ServerRequest('GET', 'https://example.test/'))?->id());
    }

    private function middleware(
        SessionGuard $guard,
        InMemoryUserProvider $users,
        DatabaseRememberTokenStore $store,
        ?CookieQueue $queue = null,
    ): RememberRestoreMiddleware {
        $queue ??= new CookieQueue();

        return new RememberRestoreMiddleware(
            guard: $guard,
            users: $users,
            tokens: $store,
            extractor: new RememberTokenExtractor(),
            cookies: new RememberCookieManager($queue),
        );
    }

    private function users(): InMemoryUserProvider
    {
        return new InMemoryUserProvider(
            users: [
                new AuthTestUser(id: 10, email: 'first@example.test', passwordHash: password_hash('secret', PASSWORD_DEFAULT)),
                new AuthTestUser(id: 20, email: 'second@example.test', passwordHash: password_hash('secret', PASSWORD_DEFAULT)),
            ],
            credentialsMatcher: static fn (UserInterface $user, array $credentials): bool => $user instanceof AuthTestUser
                && $user->email === ($credentials['email'] ?? null),
            validators: [
                new PasswordCredentialsValidator(
                    passwordHashResolver: static fn (UserInterface $user): ?string => $user instanceof AuthTestUser
                        ? $user->passwordHash
                        : null,
                ),
            ],
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

    private function cookieNameAndValue(CookieQueue $queue): string
    {
        $cookie = $queue->flush()[0] ?? null;

        return $cookie === null ? '' : $cookie->name() . '=' . $cookie->value();
    }
}
