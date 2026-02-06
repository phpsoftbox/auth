<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Exception\UnauthorizedHttpException;
use PhpSoftBox\Auth\Middleware\AuthMiddleware;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что при отсутствии пользователя выбрасывается исключение.
     */
    public function testMissingUserThrows(): void
    {
        $middleware = new AuthMiddleware(fn () => null);
        $request    = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request,
            ): \Psr\Http\Message\ResponseInterface {
                return new Response(200);
            }
        };

        $this->expectException(UnauthorizedHttpException::class);

        $middleware->process($request, $handler);
    }

    /**
     * Проверяем, что пользователь сохраняется в атрибуты запроса.
     */
    public function testUserInjectedIntoRequest(): void
    {
        $user       = ['id' => 42];
        $middleware = new AuthMiddleware(fn () => $user, required: false);
        $request    = new ServerRequest('GET', 'https://example.com/');

        $captured = null;

        $handler = new class ($captured) implements RequestHandlerInterface {
            private mixed $captured;

            public function __construct(mixed &$captured)
            {
                $this->captured = &$captured;
            }

            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request,
            ): \Psr\Http\Message\ResponseInterface {
                $this->captured = $request->getAttribute('user');

                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertSame($user, $captured);
    }
}
