<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Support;

use DateInterval;
use PhpSoftBox\Clock\Clock;
use Psr\SimpleCache\CacheInterface;

final class TestArrayCache implements CacheInterface
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
            return Clock::now()->add($ttl)->getTimestamp();
        }

        return Clock::now()->getTimestamp() + $ttl;
    }

    private function isExpired(?int $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt <= Clock::now()->getTimestamp();
    }
}
