<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie\Tests;

use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Cookie\CookieMiddleware;
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class CookieMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что Cookie заголовок парсится и Set-Cookie добавляется в ответ.
     */
    public function testParseAndSetCookie(): void
    {
        $queue = new CookieQueue();
        $middleware = new CookieMiddleware($queue);

        $request = (new ServerRequest('GET', 'https://example.com/'))
            ->withHeader('Cookie', 'a=1; b=2');

        $handler = new class ($queue) implements RequestHandlerInterface {
            public function __construct(private CookieQueue $queue)
            {
            }

            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request
            ): \Psr\Http\Message\ResponseInterface {
                $this->queue->queue(SetCookie::create('c', '3'));
                $value = (string) ($request->getCookieParams()['a'] ?? '');

                return new Response(200, ['X-A' => $value]);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame('1', $response->getHeaderLine('X-A'));
        $this->assertStringContainsString('c=3', $response->getHeaderLine('Set-Cookie'));
    }
}
