<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function array_values;
use function is_string;
use function trim;

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

    public function allow(string|PermissionGrant $permission, ?array $actions = null, ?string $scope = null): self
    {
        if (is_string($permission) && $actions !== null) {
            $permission = new PermissionGrant($permission, $actions, $scope);
        }

        $this->grants[] = $permission;

        return $this;
    }

    /**
     * @param list<PermissionGrant|string> $permissions
     * @param list<PermissionActionEnum|string>|null $actions
     */
    public function allowMany(array $permissions, ?array $actions = null, ?string $scope = null): self
    {
        foreach ($permissions as $permission) {
            $this->allow($permission, $actions, $scope);
        }

        return $this;
    }

    public function deny(string $permission): self
    {
        $permission = trim($permission);
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
