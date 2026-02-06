<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

use function array_values;

final class RoleDefinition
{
    private bool $allowAll = false;

    /**
     * @var list<PermissionGrant|string>
     */
    private array $grants = [];

    /**
     * @var list<string>
     */
    private array $denied = [];

    public function __construct(
        public string $name,
        public ?string $label = null,
        public bool $adminAccess = false,
        public bool $root = false,
    ) {
    }

    public static function named(string $name, ?string $label = null): self
    {
        return new self($name, $label);
    }

    public static function root(string $name = 'root', ?string $label = 'Root'): self
    {
        return new self($name, $label, adminAccess: true, root: true);
    }

    public static function admin(string $name = 'admin', ?string $label = 'Администратор'): self
    {
        return new self($name, $label, adminAccess: true);
    }

    public function allowAll(): self
    {
        $this->allowAll = true;

        return $this;
    }

    public function allow(string|BackedEnum|PermissionGrant $permission, ?array $actions = null, ?string $scope = null): self
    {
        if (!$permission instanceof PermissionGrant && $actions !== null) {
            $permission = new PermissionGrant($permission, $actions, $scope);
        } elseif (!$permission instanceof PermissionGrant) {
            $permission = PermissionName::normalize($permission);
        }

        $this->grants[] = $permission;

        return $this;
    }

    /**
     * @param list<PermissionGrant|string|BackedEnum> $permissions
     * @param list<BackedEnum|string>|null $actions
     */
    public function allowMany(array $permissions, ?array $actions = null, ?string $scope = null): self
    {
        foreach ($permissions as $permission) {
            $this->allow($permission, $actions, $scope);
        }

        return $this;
    }

    public function deny(string|BackedEnum $permission): self
    {
        $permission = PermissionName::normalize($permission);
        if ($permission !== '') {
            $this->denied[] = $permission;
        }

        return $this;
    }

    public function allowsAll(): bool
    {
        return $this->allowAll;
    }

    /**
     * @return list<PermissionGrant|string>
     */
    public function grants(): array
    {
        return $this->grants;
    }

    /**
     * @return list<string>
     */
    public function denied(): array
    {
        return array_values($this->denied);
    }
}
