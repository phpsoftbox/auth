<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Exception\PayloadTooLargeHttpException;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Application\Middleware\RequestSizeLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestSizeLimitMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что превышение размера запроса вызывает исключение.
     */
    public function testThrowsWhenContentLengthTooLarge(): void
    {
        $middleware = new RequestSizeLimitMiddleware(100);

        $request = (new ServerRequest('POST', 'https://example.com/'))
            ->withHeader('Content-Length', '101');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request
            ): \Psr\Http\Message\ResponseInterface {
                return new Response(200);
            }
        };

        $this->expectException(PayloadTooLargeHttpException::class);

        $middleware->process($request, $handler);
    }

    /**
     * Проверяем, что запрос проходит при допустимом размере.
     */
    public function testAllowsWhenContentLengthWithinLimit(): void
    {
        $middleware = new RequestSizeLimitMiddleware(100);

        $request = (new ServerRequest('POST', 'https://example.com/'))
            ->withHeader('Content-Length', '100');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request
            ): \Psr\Http\Message\ResponseInterface {
                return new Response(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }
}
