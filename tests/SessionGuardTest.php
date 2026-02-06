<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\ArraySessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function password_hash;

use const PASSWORD_DEFAULT;

#[CoversClass(SessionGuard::class)]
#[CoversMethod(SessionGuard::class, 'attempt')]
#[CoversMethod(SessionGuard::class, 'user')]
#[CoversMethod(SessionGuard::class, 'login')]
#[CoversMethod(SessionGuard::class, 'logout')]
final class SessionGuardTest extends TestCase
{
    /**
     * Проверяем, что attempt сохраняет идентификатор пользователя в сессии.
     */
    #[Test]
    public function testAttemptStoresUserId(): void
    {
        $users = $this->users();

        $session = new Session(new ArraySessionStore());

        $session->start();

        $guard = new SessionGuard($session, $users);

        $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

        $this->assertTrue($result);
        $this->assertSame(10, $session->get('auth.user_id'));
    }

    /**
     * Проверяем, что guard возвращает пользователя по данным сессии.
     */
    #[Test]
    public function testUserResolvedFromSession(): void
    {
        $users = $this->users();

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 10);

        $guard = new SessionGuard($session, $users);

        $user = $guard->user(new ServerRequest('GET', 'https://example.com/'));

        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertSame(10, $user->id());
    }

    /**
     * Проверяем, что attempt сохраняет в сессии хэш пользователя, если включена проверка.
     */
    #[Test]
    public function testAttemptStoresUserHashWhenEnabled(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);

        $users = $this->users($hash);

        $session = new Session(new ArraySessionStore());

        $session->start();

        $guard = new SessionGuard(
            $session,
            $users,
            sessionHashKey: 'auth.user_hash',
            userStampResolver: static fn (UserInterface $user): ?string => $user instanceof AuthTestUser
                ? $user->passwordHash
                : null,
        );

        $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

        $this->assertTrue($result);
        $this->assertSame(10, $session->get('auth.user_id'));
        $this->assertSame($hash, $session->get('auth.user_hash'));
    }

    /**
     * Проверяем, что guard сбрасывает сессию при несовпадении хэша пользователя.
     */
    #[Test]
    public function testUserResolvedFromSessionLogsOutOnHashMismatch(): void
    {
        $users = $this->users();

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 10);
        $session->set('auth.user_hash', password_hash('other-secret', PASSWORD_DEFAULT));

        $guard = new SessionGuard(
            $session,
            $users,
            sessionHashKey: 'auth.user_hash',
            userStampResolver: static fn (UserInterface $user): ?string => $user instanceof AuthTestUser
                ? $user->passwordHash
                : null,
        );

        $user = $guard->user(new ServerRequest('GET', 'https://example.com/'));

        $this->assertNull($user);
        $this->assertNull($session->get('auth.user_id'));
        $this->assertNull($session->get('auth.user_hash'));
    }

    /**
     * Проверяем, что guard сбрасывает сессию при несовпадении auth_token.
     */
    #[Test]
    public function testUserResolvedFromSessionLogsOutOnAuthTokenMismatch(): void
    {
        $users = $this->users(authToken: 'token-new');

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 10);
        $session->set('auth.user_hash', 'token-old');

        $guard = new SessionGuard(
            $session,
            $users,
            sessionHashKey: 'auth.user_hash',
            userStampResolver: static fn (UserInterface $user): ?string => $user instanceof AuthTestUser
                ? $user->authToken
                : null,
        );

        $user = $guard->user(new ServerRequest('GET', 'https://example.com/'));

        $this->assertNull($user);
        $this->assertNull($session->get('auth.user_id'));
        $this->assertNull($session->get('auth.user_hash'));
    }

    /**
     * Проверяем, что при login() выполняется регенерация id сессии.
     */
    #[Test]
    public function testLoginRegeneratesSessionId(): void
    {
        $users = $this->users();

        $session = new TrackingSession();

        $session->start();

        $guard = new SessionGuard($session, $users);

        $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

        $this->assertTrue($result);
        $this->assertSame(1, $session->regenerateCalls);
    }

    private function users(?string $passwordHash = null, ?string $authToken = null): InMemoryUserProvider
    {
        $passwordHash ??= password_hash('secret', PASSWORD_DEFAULT);

        return new InMemoryUserProvider(
            users: [
                new AuthTestUser(
                    id: 10,
                    email: 'test@example.com',
                    passwordHash: $passwordHash,
                    authToken: $authToken,
                ),
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
}
