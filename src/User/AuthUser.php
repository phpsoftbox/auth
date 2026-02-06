<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\User;

use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function array_key_exists;
use function ctype_digit;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function property_exists;

final class AuthUser implements UserDataInterface, UserIdentityInterface
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private array $attributes,
        private readonly string $idField = 'id',
        private readonly mixed $identity = null,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        $id = $this->attributes[$this->idField] ?? null;

        $resolved = $this->resolveId($id);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($this->identity instanceof UserIdentityInterface) {
            return $this->identity->id();
        }

        if (is_object($this->identity) && property_exists($this->identity, $this->idField)) {
            return $this->resolveId($this->identity->{$this->idField});
        }

        return null;
    }

    private function resolveId(mixed $id): int|UuidInterface|null
    {
        if (is_int($id)) {
            return $id;
        }

        if (!is_string($id)) {
            return null;
        }

        if (ctype_digit($id)) {
            return (int) $id;
        }

        if (Uuid::isValid($id)) {
            return Uuid::fromString($id);
        }

        return null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if ($this->identity instanceof UserDataInterface) {
            return $this->identity->get($key, $default);
        }

        if (is_array($this->identity) && array_key_exists($key, $this->identity)) {
            return $this->identity[$key];
        }

        if (is_object($this->identity) && property_exists($this->identity, $key)) {
            return $this->identity->$key;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function identity(?string $className = null): mixed
    {
        return $this->identity ?? $this->attributes;
    }

    /**
     * @deprecated use identity() instead
     */
    public function data(): mixed
    {
        return $this->identity();
    }
}
