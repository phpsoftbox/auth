<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;

final class TokenGuardTest extends TestCase
{
    /**
     * Проверяем, что guard извлекает пользователя из токена.
     */
    public function testUserResolvedFromBearerToken(): void
    {
        $users = new ArrayUserProvider([
            ['id' => 5, 'email' => 'test@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $tokens = new ArrayTokenProvider([
            'token-123' => 5,
        ]);

        $guard = new TokenGuard($tokens, $users);

        $request = new ServerRequest('GET', 'https://example.com/', [
            'Authorization' => 'Bearer token-123',
        ]);

        $user = $guard->user($request);

        $this->assertIsArray($user);
        $this->assertSame(5, $user['id']);
    }
}
