<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Auth\Tests\Support\AuthTestUser;
use PhpSoftBox\Auth\Token\BearerTokenExtractor;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;

final class TokenGuardTest extends TestCase
{
    /**
     * Проверяем, что guard извлекает пользователя из токена.
     */
    public function testUserResolvedFromBearerToken(): void
    {
        $users = new InMemoryUserProvider([
            new AuthTestUser(id: 5, email: 'test@example.com'),
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
        $users = new InMemoryUserProvider([
            new AuthTestUser(id: 5, email: 'test@example.com'),
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
        $users = new InMemoryUserProvider([
            new AuthTestUser(id: 5, email: 'test@example.com'),
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
