<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\ArraySessionStore;
use PhpSoftBox\Session\Session;
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
        $users = new ArrayUserProvider([
            [
                'id'            => 10,
                'email'         => 'test@example.com',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ],
        ]);

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
        $users = new ArrayUserProvider([
            [
                'id'            => 10,
                'email'         => 'test@example.com',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ],
        ]);

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 10);

        $guard = new SessionGuard($session, $users);

        $user = $guard->user(new ServerRequest('GET', 'https://example.com/'));

        $this->assertInstanceOf(UserDataInterface::class, $user);
        $this->assertInstanceOf(UserIdentityInterface::class, $user);
        $this->assertSame(10, $user->getId());
    }

    /**
     * Проверяем, что attempt сохраняет в сессии хэш пользователя, если включена проверка.
     */
    #[Test]
    public function testAttemptStoresUserHashWhenEnabled(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);

        $users = new ArrayUserProvider([
            [
                'id'            => 10,
                'email'         => 'test@example.com',
                'password_hash' => $hash,
            ],
        ]);

        $session = new Session(new ArraySessionStore());

        $session->start();

        $guard = new SessionGuard($session, $users, sessionHashKey: 'auth.user_hash');

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
        $users = new ArrayUserProvider([
            [
                'id'            => 10,
                'email'         => 'test@example.com',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ],
        ]);

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 10);
        $session->set('auth.user_hash', password_hash('other-secret', PASSWORD_DEFAULT));

        $guard = new SessionGuard($session, $users, sessionHashKey: 'auth.user_hash');

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
        $users = new ArrayUserProvider([
            [
                'id'            => 10,
                'email'         => 'test@example.com',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
                'auth_token'    => 'token-new',
            ],
        ]);

        $session = new Session(new ArraySessionStore());

        $session->start();
        $session->set('auth.user_id', 10);
        $session->set('auth.user_hash', 'token-old');

        $guard = new SessionGuard($session, $users, sessionHashKey: 'auth.user_hash', userHashKey: 'auth_token');

        $user = $guard->user(new ServerRequest('GET', 'https://example.com/'));

        $this->assertNull($user);
        $this->assertNull($session->get('auth.user_id'));
        $this->assertNull($session->get('auth.user_hash'));
    }
}
