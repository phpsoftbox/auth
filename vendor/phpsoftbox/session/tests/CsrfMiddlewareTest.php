<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Application\Exception\HttpException;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\CsrfMiddleware;
use PhpSoftBox\Session\Tests\Fixtures\SessionStoreSpy;
use PhpSoftBox\Session\Session;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddlewareTest extends TestCase
{
    /**
     * Проверяем генерацию токена и его наличие в атрибуте.
     */
    public function testGeneratesToken(): void
    {
        $session = new Session(new SessionStoreSpy());
        $middleware = new CsrfMiddleware($session);

        $request = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request
            ): ResponseInterface {
                return new Response(200, ['X-Token' => (string) $request->getAttribute('csrf_token')]);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertNotSame('', $response->getHeaderLine('X-Token'));
    }

    /**
     * Проверяем, что при неверном токене выбрасывается исключение.
     */
    public function testInvalidTokenThrows(): void
    {
        $session = new Session(new SessionStoreSpy());
        $middleware = new CsrfMiddleware($session);

        $request = (new ServerRequest('POST', 'https://example.com/', parsedBody: ['_token' => 'bad']));

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $this->expectException(HttpException::class);
        $middleware->process($request, $handler);
    }
}
