<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\GuardInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Auth\Middleware\GuardMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class GuardMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что пользователь и user_id сохраняются в атрибуты запроса.
     */
    public function testInjectsUserAndUserIdWhenGuardReturnsIdentity(): void
    {
        $user = new class () implements UserInterface {
            public function id(): int|string|null
            {
                return 42;
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $default;
            }

            public function identity(?string $className = null): mixed
            {
                return $this;
            }
        };

        $guard = new class ($user) implements GuardInterface {
            public int $calls = 0;

            public function __construct(
                private mixed $user,
            ) {
            }

            public function user(ServerRequestInterface $request): mixed
            {
                $this->calls++;

                return $this->user;
            }
        };

        $auth = new AuthManager(['web' => $guard]);

        $middleware = new GuardMiddleware($auth);
        $request    = new ServerRequest('GET', 'https://example.com/');

        $captured = null;

        $handler = new class ($captured) implements RequestHandlerInterface {
            private mixed $captured;

            public function __construct(mixed &$captured)
            {
                $this->captured = &$captured;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame(1, $guard->calls);
        $this->assertInstanceOf(ServerRequestInterface::class, $captured);
        $this->assertSame($user, $captured->getAttribute('user'));
        $this->assertSame(42, $captured->getAttribute('user_id'));
    }

    /**
     * Проверяем, что при наличии user атрибута guard не вызывается.
     */
    public function testExistingUserAttributeSkipsGuard(): void
    {
        $existingUser = ['id' => 99];

        $guard = new class () implements GuardInterface {
            public int $calls = 0;

            public function user(ServerRequestInterface $request): mixed
            {
                $this->calls++;

                return null;
            }
        };

        $auth = new AuthManager(['web' => $guard]);

        $middleware = new GuardMiddleware($auth);

        $request = new ServerRequest(
            'GET',
            'https://example.com/',
            attributes: ['user' => $existingUser],
        );

        $captured = null;

        $handler = new class ($captured) implements RequestHandlerInterface {
            private mixed $captured;

            public function __construct(mixed &$captured)
            {
                $this->captured = &$captured;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request;

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame(0, $guard->calls);
        $this->assertSame($existingUser, $captured->getAttribute('user'));
    }
}
