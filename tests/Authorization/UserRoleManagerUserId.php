<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use Ramsey\Uuid\UuidInterface;

final class UserRoleManagerUserId implements UserIdentityInterface
{
    public function __construct(
        private readonly int
    $id,
    ) {
    }

    public function id(): int|UuidInterface|null
    {
        return $this->id;
    }
}
