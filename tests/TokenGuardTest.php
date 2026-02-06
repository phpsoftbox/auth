<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Auth\Token\BearerTokenExtractor;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;

use function password_hash;

use const PASSWORD_DEFAULT;

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
        ], $users);

        $guard = new TokenGuard($tokens);

        $request = new ServerRequest('GET', 'https://example.com/', [
            'Authorization' => 'Bearer token-123',
        ]);

        $user = $guard->user($request);

        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertSame(5, $user->id());
    }

    /**
     * Проверяем, что по умолчанию токен из query не используется.
     */
    public function testQueryTokenIsIgnoredByDefault(): void
    {
        $users = new ArrayUserProvider([
            ['id' => 5, 'email' => 'test@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $tokens = new ArrayTokenProvider([
            'token-123' => 5,
        ], $users);

        $guard = new TokenGuard($tokens);

        $request = new ServerRequest('GET', 'https://example.com/')
            ->withQueryParams(['access_token' => 'token-123']);

        $this->assertNull($guard->user($request));
    }

    /**
     * Проверяем, что токен из query можно включить явно через extractor.
     */
    public function testQueryTokenCanBeEnabledExplicitly(): void
    {
        $users = new ArrayUserProvider([
            ['id' => 5, 'email' => 'test@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
        ]);

        $tokens = new ArrayTokenProvider([
            'token-123' => 5,
        ], $users);

        $guard = new TokenGuard($tokens, new BearerTokenExtractor(queryParams: ['access_token']));

        $request = new ServerRequest('GET', 'https://example.com/')
            ->withQueryParams(['access_token' => 'token-123']);

        $user = $guard->user($request);

        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertSame(5, $user->id());
    }
}
