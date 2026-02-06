<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

interface SessionInterface
{
    public function start(): void;
    public function isStarted(): bool;
    public function all(): array;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function forget(string $key): void;
    public function clear(): void;
    public function flash(string $key, mixed $value): void;
    public function getFlash(string $key, mixed $default = null): mixed;
    public function pull(string $key, mixed $default = null): mixed;
    public function save(): void;
    public function regenerate(bool $deleteOldSession = true): void;
    public function destroy(): void;
}
