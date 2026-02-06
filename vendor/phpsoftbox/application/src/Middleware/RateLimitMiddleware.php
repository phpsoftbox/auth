<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Middleware;

use Closure;
use PhpSoftBox\Application\Exception\TooManyRequestsHttpException;
use PhpSoftBox\RateLimiter\RateLimiterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_callable;
use function sprintf;

final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param RateLimiterInterface $limiter
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @param Closure|null $keyResolver fn(ServerRequestInterface $request): string
     */
    public function __construct(
        private readonly RateLimiterInterface $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
        private readonly ?Closure $keyResolver = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveKey($request);

        $result = $this->limiter->hit($key, $this->maxAttempts, $this->decaySeconds);
        if (!$result->allowed) {
            throw new TooManyRequestsHttpException(
                message: 'Too Many Requests',
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $result->remaining)
            ->withHeader('X-RateLimit-Reset', (string) $result->retryAfter);
    }

    private function resolveKey(ServerRequestInterface $request): string
    {
        if ($this->keyResolver && is_callable($this->keyResolver)) {
            return ($this->keyResolver)($request);
        }

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $path = $request->getUri()->getPath();

        return sprintf('%s|%s', $ip, $path);
    }
}
