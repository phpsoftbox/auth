<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateInterval;
use DateTime;
use PhpSoftBox\Auth\Otp\CacheOtpValidator;
use PhpSoftBox\Auth\Otp\OtpCodeGenerator;
use PhpSoftBox\Auth\Otp\OtpState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

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
}

final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{value:mixed, expires_at:?int}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->store[$key] ?? null;
        if ($entry === null) {
            return $default;
        }

        if ($this->isExpired($entry['expires_at'])) {
            unset($this->store[$key]);

            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->store[$key] = [
            'value'      => $value,
            'expires_at' => $this->ttlToExpiresAt($ttl),
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $entry = $this->store[$key] ?? null;
        if ($entry === null) {
            return false;
        }

        if ($this->isExpired($entry['expires_at'])) {
            unset($this->store[$key]);

            return false;
        }

        return true;
    }

    private function ttlToExpiresAt(DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            $now = new DateTime('now');

            $now->add($ttl);

            return $now->getTimestamp();
        }

        return time() + $ttl;
    }

    private function isExpired(?int $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt <= time();
    }
}
