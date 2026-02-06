<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Otp;

use Psr\SimpleCache\CacheInterface;

use function hash_equals;
use function is_array;
use function is_string;
use function max;
use function time;

final class CacheOtpValidator implements OtpValidatorInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly OtpCodeGenerator $generator = new OtpCodeGenerator(),
        private readonly int $ttlSeconds = 300,
        private readonly int $maxAttempts = 3,
        private readonly int $lockSeconds = 1800,
        private readonly string $prefix = 'otp',
    ) {
    }

    public function issue(string $identifier, ?int $length = null): OtpState
    {
        $now       = time();
        $ttl       = max(1, $this->ttlSeconds);
        $expiresAt = $now + $ttl;
        $code      = $this->generator->generate($length ?? 6);

        $payload = [
            'code'         => $code,
            'attempts'     => 0,
            'expires_at'   => $expiresAt,
            'locked_until' => null,
        ];

        $this->cache->set($this->key($identifier), $payload, $ttl);

        return new OtpState($code, 0, $this->maxAttempts, $expiresAt, null);
    }

    public function state(string $identifier): ?OtpState
    {
        $payload = $this->payload($identifier);
        if ($payload === null) {
            return null;
        }

        return new OtpState(
            code: $payload['code'] ?? null,
            attempts: (int) ($payload['attempts'] ?? 0),
            maxAttempts: $this->maxAttempts,
            expiresAt: isset($payload['expires_at']) ? (int) $payload['expires_at'] : null,
            lockedUntil: isset($payload['locked_until']) ? (int) $payload['locked_until'] : null,
        );
    }

    public function validate(string $identifier, string $code): bool
    {
        $payload = $this->payload($identifier);
        if ($payload === null) {
            return false;
        }

        $now         = time();
        $lockedUntil = isset($payload['locked_until']) ? (int) $payload['locked_until'] : null;
        if ($lockedUntil !== null && $lockedUntil > $now) {
            return false;
        }

        $expected = $payload['code'] ?? null;
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        if (!hash_equals($expected, $code)) {
            $attempts            = (int) ($payload['attempts'] ?? 0) + 1;
            $payload['attempts'] = $attempts;

            if ($attempts >= $this->maxAttempts) {
                $payload['locked_until'] = $now + max(1, $this->lockSeconds);
                $payload['expires_at']   = $payload['locked_until'];
                $this->cache->set($this->key($identifier), $payload, max(1, $this->lockSeconds));

                return false;
            }

            $this->storePayload($identifier, $payload, $now);

            return false;
        }

        $this->cache->delete($this->key($identifier));

        return true;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function attemptsLeft(string $identifier): int
    {
        $payload = $this->payload($identifier);
        if ($payload === null) {
            return $this->maxAttempts;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        $left     = $this->maxAttempts - $attempts;

        return $left > 0 ? $left : 0;
    }

    public function resetAttempts(string $identifier): void
    {
        $payload = $this->payload($identifier);
        if ($payload === null) {
            return;
        }

        $payload['attempts']     = 0;
        $payload['locked_until'] = null;
        $this->storePayload($identifier, $payload, time());
    }

    private function key(string $identifier): string
    {
        return $this->prefix . '.' . $identifier;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(string $identifier): ?array
    {
        $payload = $this->cache->get($this->key($identifier));

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storePayload(string $identifier, array $payload, int $now): void
    {
        $expiresAt = isset($payload['expires_at']) ? (int) $payload['expires_at'] : null;
        $ttl       = $expiresAt !== null ? max(1, $expiresAt - $now) : max(1, $this->ttlSeconds);

        $this->cache->set($this->key($identifier), $payload, $ttl);
    }
}
