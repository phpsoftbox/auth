<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Contracts\UserRolesInterface;

final class UserRoleResolver implements RoleResolverInterface
{
    public function resolve(mixed $user): array
    {
        if (!$user instanceof UserRolesInterface) {
            return [];
        }

        return $user->getRoleNames();
    }
}
