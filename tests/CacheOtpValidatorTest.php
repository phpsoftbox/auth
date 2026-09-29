<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Exception\OtpLockedException;
use PhpSoftBox\Auth\Otp\CacheOtpValidator;
use PhpSoftBox\Auth\Otp\OtpCodeGenerator;
use PhpSoftBox\Auth\Otp\OtpState;
use PhpSoftBox\RateLimiter\RateLimiterInterface;
use PhpSoftBox\RateLimiter\RateLimitResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function max;
use function time;

#[CoversClass(CacheOtpValidator::class)]
final class CacheOtpValidatorTest extends TestCase
{
    #[Test]
    public function issuesAndValidatesCode(): void
    {
        $cache = new ArrayCache();

        $validator = new CacheOtpValidator($cache, new OtpCodeGenerator(), ttlSeconds: 120, maxAttempts: 3, lockSeconds: 900);

        $state = $validator->issue('user.1', 4);

        self::assertInstanceOf(OtpState::class, $state);
        self::assertNotNull($state->code());
        self::assertSame(3, $state->attemptsLeft());

        $isValid = $validator->validate('user.1', (string) $state->code());
        self::assertTrue($isValid);
        self::assertNull($validator->state('user.1'));
    }

    #[Test]
    public function locksAfterMaxAttempts(): void
    {
        $cache = new ArrayCache();

        $validator = new CacheOtpValidator($cache, new OtpCodeGenerator(), ttlSeconds: 120, maxAttempts: 2, lockSeconds: 60);

        $state = $validator->issue('user.2', 4);

        self::assertFalse($validator->validate('user.2', '0000'));
        self::assertFalse($validator->validate('user.2', '0000'));

        $lockedState = $validator->state('user.2');
        self::assertNotNull($lockedState);
        self::assertTrue($lockedState->isLocked());
        self::assertSame(0, $lockedState->attemptsLeft());
    }

    /**
     * Проверим, что пока идентификатор заблокирован, новый код не выдаётся и блокировка не сбрасывается.
     *
     * @see CacheOtpValidator::issue()
     */
    #[Test]
    public function issueKeepsLock(): void
    {
        $validator = new CacheOtpValidator(new ArrayCache(), new OtpCodeGenerator(), ttlSeconds: 120, maxAttempts: 1, lockSeconds: 60);

        $validator->issue('user.3', 4);
        $validator->validate('user.3', 'wrong');

        $this->expectException(OtpLockedException::class);

        $validator->issue('user.3', 4);
    }

    /**
     * Проверим, что с атомарным лимитером попытка резервируется до сравнения: сверх лимита не принимается даже верный
     * код (параллельные запросы не дают лишних попыток).
     *
     * @see CacheOtpValidator::validate()
     */
    #[Test]
    public function limiterRejectsAttemptsOverLimit(): void
    {
        $limiter = new class () implements RateLimiterInterface {
            /** @var array<string, int> */
            private array $hits = [];

            public function hit(string $key, int $maxAttempts, int $decaySeconds): RateLimitResult
            {
                $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;
                $allowed          = $this->hits[$key] <= $maxAttempts;

                return new RateLimitResult($allowed, $maxAttempts, max(0, $maxAttempts - $this->hits[$key]), 60, time() + 60);
            }

            public function attempts(string $key): int
            {
                return $this->hits[$key] ?? 0;
            }

            public function reset(string $key): void
            {
                unset($this->hits[$key]);
            }
        };

        $validator = new CacheOtpValidator(new ArrayCache(), new OtpCodeGenerator(), ttlSeconds: 120, maxAttempts: 2, lockSeconds: 60, attemptLimiter: $limiter);

        $code = (string) $validator->issue('user.4', 4)->code();

        // Два «параллельных» запроса уже израсходовали лимит в атомарном хранилище.
        $limiter->hit('otp.user.4.' . $this->nonce($validator, 'user.4'), 2, 60);
        $limiter->hit('otp.user.4.' . $this->nonce($validator, 'user.4'), 2, 60);

        self::assertFalse($validator->validate('user.4', $code));
        self::assertTrue($validator->state('user.4')?->isLocked());
    }

    private function nonce(CacheOtpValidator $validator, string $identifier): string
    {
        $payload = (fn (): ?array => $this->payload($identifier))->call($validator);

        return (string) ($payload['nonce'] ?? '');
    }
}
