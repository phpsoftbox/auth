<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Contracts\UserRolesInterface;

final class RoleUser implements UserRolesInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private array $roles,
    ) {
    }

    public function getRoleNames(): array
    {
        return $this->roles;
    }
}
