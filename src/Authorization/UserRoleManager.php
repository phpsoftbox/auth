<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;
use PhpSoftBox\Auth\Authorization\Store\UserRoleStoreInterface;
use PhpSoftBox\Auth\Contracts\UserDataInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Auth\Exception\RoleNotAssignedException;
use PhpSoftBox\Auth\Exception\RoleNotFoundException;

use function array_diff;
use function array_unique;
use function array_values;
use function ctype_digit;
use function is_int;
use function is_string;

final class UserRoleManager
{
    public function __construct(
        private readonly UserRoleStoreInterface $userRoles,
        private readonly RoleStoreInterface $roles,
        private readonly string $userIdField = 'id',
    ) {
    }

    /**
     * @return list<string>
     */
    public function roles(mixed $user): array
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            return [];
        }

        return $this->userRoles->listRoleNamesByUserId($userId);
    }

    public function role(mixed $user): ?string
    {
        $roles = $this->roles($user);

        return $roles[0] ?? null;
    }

    /**
     * @throws RoleNotAssignedException
     */
    public function requireRole(mixed $user): string
    {
        $role = $this->role($user);
        if ($role === null) {
            throw new RoleNotAssignedException('User has no roles.');
        }

        return $role;
    }

    /**
     * @return list<string>
     * @throws RoleNotAssignedException
     */
    public function requireRoles(mixed $user): array
    {
        $roles = $this->roles($user);
        if ($roles === []) {
            throw new RoleNotAssignedException('User has no roles.');
        }

        return $roles;
    }

    /**
     * @throws RoleNotFoundException
     */
    public function assignRole(mixed $user, string $roleName): void
    {
        $this->assignRoles($user, [$roleName]);
    }

    /**
     * @param list<string> $roleNames
     * @throws RoleNotFoundException
     */
    public function assignRoles(mixed $user, array $roleNames, bool $replace = false): void
    {
        $userId = $this->requireUserId($user);

        $roleNames = array_values(array_unique($roleNames));
        if ($roleNames === []) {
            if ($replace) {
                $this->userRoles->detachAll($userId);
            }

            return;
        }

        $roleIds = $this->resolveRoleIds($roleNames);

        if ($replace) {
            $this->userRoles->detachAll($userId);
            foreach ($roleIds as $roleId) {
                $this->userRoles->attach($userId, $roleId);
            }

            return;
        }

        $current  = $this->userRoles->listRoleIdsByUserId($userId);
        $toAttach = array_diff($roleIds, $current);

        foreach ($toAttach as $roleId) {
            $this->userRoles->attach($userId, $roleId);
        }
    }

    /**
     * @throws RoleNotFoundException
     */
    public function removeRole(mixed $user, string $roleName): void
    {
        $userId = $this->requireUserId($user);
        $roleId = $this->roles->findIdByName($roleName);
        if ($roleId === null) {
            throw new RoleNotFoundException('Role not found: ' . $roleName);
        }

        $this->userRoles->detach($userId, $roleId);
    }

    /**
     * @param list<string> $roleNames
     * @throws RoleNotFoundException
     */
    public function replaceRoles(mixed $user, array $roleNames): void
    {
        $this->assignRoles($user, $roleNames, replace: true);
    }

    private function resolveUserId(mixed $user): ?int
    {
        if (is_int($user)) {
            return $user;
        }

        if ($user instanceof UserIdentityInterface) {
            $userId = $user->getId();
        } elseif ($user instanceof UserDataInterface) {
            $userId = $user->get($this->userIdField);
        } else {
            return null;
        }

        if (!is_int($userId)) {
            if (is_string($userId) && ctype_digit($userId)) {
                return (int) $userId;
            }

            return null;
        }

        return $userId;
    }

    private function requireUserId(mixed $user): int
    {
        $userId = $this->resolveUserId($user);
        if ($userId === null) {
            throw new RoleNotAssignedException('User id is missing for role assignment.');
        }

        return $userId;
    }

    /**
     * @param list<string> $roleNames
     * @return list<int>
     * @throws RoleNotFoundException
     */
    private function resolveRoleIds(array $roleNames): array
    {
        $roleIds = [];
        foreach ($roleNames as $roleName) {
            $roleId = $this->roles->findIdByName($roleName);
            if ($roleId === null) {
                throw new RoleNotFoundException('Role not found: ' . $roleName);
            }
            $roleIds[] = $roleId;
        }

        return array_values(array_unique($roleIds));
    }
}
