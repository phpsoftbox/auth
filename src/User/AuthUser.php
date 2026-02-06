<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\User;

use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;

use function array_key_exists;
use function is_int;
use function is_string;

final class AuthUser implements UserDataInterface, UserIdentityInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private array $data,
        private readonly string $idField = 'id',
    ) {
    }

    public function getId(): int|string|null
    {
        $id = $this->data[$this->idField] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
