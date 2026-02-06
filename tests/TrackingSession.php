<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Session\SessionInterface;

final class TrackingSession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data         = [];
    private bool $started       = false;
    public int $regenerateCalls = 0;

    public function start(): void
    {
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function flash(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function save(): void
    {
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->regenerateCalls++;
    }

    public function destroy(): void
    {
        $this->data    = [];
        $this->started = false;
    }
}
