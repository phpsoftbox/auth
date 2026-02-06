<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\Exception\TooManyRequestsHttpException;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Application\Middleware\RateLimitMiddleware;
use PhpSoftBox\Application\Tests\Fixtures\ArrayCache;
use PhpSoftBox\RateLimiter\SimpleCacheRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что после превышения лимита бросается исключение.
     */
    public function testRateLimitExceeded(): void
    {
        $cache = new ArrayCache();
        $limiter = new SimpleCacheRateLimiter($cache);
        $middleware = new RateLimitMiddleware($limiter, maxAttempts: 1, decaySeconds: 60);

        $request = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                \Psr\Http\Message\ServerRequestInterface $request
            ): \Psr\Http\Message\ResponseInterface {
                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->expectException(TooManyRequestsHttpException::class);
        $middleware->process($request, $handler);
    }
}
