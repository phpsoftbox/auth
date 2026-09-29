<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Otp;

use PhpSoftBox\Auth\Exception\OtpLockedException;
use PhpSoftBox\RateLimiter\RateLimiterInterface;
use Psr\SimpleCache\CacheInterface;

use function bin2hex;
use function hash_equals;
use function is_array;
use function is_string;
use function max;
use function random_bytes;
use function time;

/**
 * Одноразовые коды в PSR-16 кеше.
 *
 * Попытки считаются в payload кода, но PSR-16 не умеет атомарно увеличивать счётчик: N параллельных запросов успевают
 * сделать N попыток. Чтобы лимит нельзя было обойти параллельными запросами, передайте `$attemptLimiter` с атомарным
 * хранилищем (например, `RedisRateLimiter`): попытка резервируется в нём до сравнения кода.
 *
 * Пока идентификатор заблокирован, новый код не выдаётся — {@see OtpLockedException}.
 */
final class CacheOtpValidator implements OtpValidatorInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly OtpCodeGenerator $generator = new OtpCodeGenerator(),
        private readonly int $ttlSeconds = 300,
        private readonly int $maxAttempts = 3,
        private readonly int $lockSeconds = 1800,
        private readonly string $prefix = 'otp',
        private readonly ?RateLimiterInterface $attemptLimiter = null,
    ) {
    }

    public function issue(string $identifier, ?int $length = null): OtpState
    {
        $now = time();

        // Новый код не снимает блокировку после исчерпания попыток.
        $lockedUntil = $this->lockedUntil($this->payload($identifier));
        if ($lockedUntil !== null && $lockedUntil > $now) {
            throw new OtpLockedException($lockedUntil);
        }

        $ttl       = max(1, $this->ttlSeconds);
        $expiresAt = $now + $ttl;
        $code      = $this->generator->generate($length ?? 6);

        $payload = [
            'code'             => $code,
            'attempts'         => 0,
            'expires_datetime' => $expiresAt,
            'locked_until'     => null,
            'nonce'            => $this->nonce(),
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
            expiresAt: isset($payload['expires_datetime']) ? (int) $payload['expires_datetime'] : null,
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

        // Попытка резервируется атомарно до сравнения: параллельные запросы сверх лимита не проверяют код вовсе.
        if ($this->attemptLimiter !== null) {
            $reserved = $this->attemptLimiter->hit(
                $this->key($identifier) . '.' . (string) ($payload['nonce'] ?? ''),
                max(1, $this->maxAttempts),
                max(1, $this->ttlSeconds, $this->lockSeconds),
            );

            if (!$reserved->allowed) {
                $this->lock($identifier, $payload, $now);

                return false;
            }
        }

        if (!hash_equals($expected, $code)) {
            $attempts            = (int) ($payload['attempts'] ?? 0) + 1;
            $payload['attempts'] = $attempts;

            if ($attempts >= $this->maxAttempts) {
                $this->lock($identifier, $payload, $now);

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
        // Новый nonce — новый счётчик в $attemptLimiter.
        $payload['nonce'] = $this->nonce();
        $this->storePayload($identifier, $payload, time());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function lock(string $identifier, array $payload, int $now): void
    {
        $payload['attempts']         = max((int) ($payload['attempts'] ?? 0), $this->maxAttempts);
        $payload['locked_until']     = $now + max(1, $this->lockSeconds);
        $payload['expires_datetime'] = $payload['locked_until'];
        $this->cache->set($this->key($identifier), $payload, max(1, $this->lockSeconds));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function lockedUntil(?array $payload): ?int
    {
        return isset($payload['locked_until']) ? (int) $payload['locked_until'] : null;
    }

    private function nonce(): string
    {
        return bin2hex(random_bytes(8));
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
        $expiresAt = isset($payload['expires_datetime']) ? (int) $payload['expires_datetime'] : null;
        $ttl       = $expiresAt !== null ? max(1, $expiresAt - $now) : max(1, $this->ttlSeconds);

        $this->cache->set($this->key($identifier), $payload, $ttl);
    }
}
