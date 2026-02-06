<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Contracts\UserInterface;

final class UserRoleManagerUserId implements UserInterface
{
    public function __construct(
        private readonly int $id,
    ) {
    }

    public function id(): int|string|null
    {
        return $this->id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function identity(?string $className = null): mixed
    {
        return $this;
    }
}
