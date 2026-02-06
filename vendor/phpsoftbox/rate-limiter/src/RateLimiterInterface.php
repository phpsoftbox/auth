<?php

declare(strict_types=1);

namespace PhpSoftBox\RateLimiter;

interface RateLimiterInterface
{
    public function hit(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult;
}
