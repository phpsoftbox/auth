<?php

declare(strict_types=1);

namespace PhpSoftBox\RateLimiter;

use Psr\SimpleCache\CacheInterface;

use function is_array;
use function time;

final class SimpleCacheRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function hit(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult
    {
        $now = time();
        $data = $this->cache->get($key);

        if (!is_array($data) || !isset($data['count'], $data['reset'])) {
            $data = ['count' => 0, 'reset' => $now + $decaySeconds];
        }

        if ($data['reset'] <= $now) {
            $data = ['count' => 0, 'reset' => $now + $decaySeconds];
        }

        $data['count']++;

        $this->cache->set($key, $data, $decaySeconds);

        $remaining = $maxAttempts - $data['count'];
        $allowed = $remaining >= 0;

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $remaining >= 0 ? $remaining : 0,
            retryAfter: $data['reset'] - $now,
        );
    }
}
