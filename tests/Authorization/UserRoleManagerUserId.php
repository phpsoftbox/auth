<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Contracts\UserIdentityInterface;

final class UserRoleManagerUserId implements UserIdentityInterface
{
    public function __construct(
        private readonly int
    $id,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
