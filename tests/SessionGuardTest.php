<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\ArraySessionStore;
use PhpSoftBox\Session\Session;
use PHPUnit\Framework\TestCase;

final class SessionGuardTest extends TestCase
{
    /**
     * Проверяем, что attempt сохраняет идентификатор пользователя в сессии.
     */
    public function testAttemptStoresUserId(): void
    {
        $users = new ArrayUserProvider([
            [
                'id' => 10,
                'email' => 'test@example.com',
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
    public function testUserResolvedFromSession(): void
    {
        $users = new ArrayUserProvider([
            [
                'id' => 10,
                'email' => 'test@example.com',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ],
        ]);

        $session = new Session(new ArraySessionStore());
        $session->start();
        $session->set('auth.user_id', 10);

        $guard = new SessionGuard($session, $users);

        $user = $guard->user(new ServerRequest('GET', 'https://example.com/'));

        $this->assertIsArray($user);
        $this->assertSame(10, $user['id']);
    }
}
