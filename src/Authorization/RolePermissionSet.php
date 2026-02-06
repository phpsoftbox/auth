<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function array_keys;
use function sort;

use const SORT_STRING;

final class RolePermissionSet
{
    /**
     * @param array<string, true> $allowed
     * @param array<string, true> $denied
     */
    public function __construct(
        public string $name,
        public bool $allowAll,
        public bool $adminAccess,
        public bool $root,
        private array $allowed,
        private array $denied,
    ) {
    }

    public function allows(string $permission): bool
    {
        if (isset($this->denied[$permission])) {
            return false;
        }

        if ($this->allowAll) {
            return true;
        }

        return isset($this->allowed[$permission]);
    }

    /**
     * @return list<string>
     */
    public function allowed(): array
    {
        $names = array_keys($this->allowed);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * @return list<string>
     */
    public function denied(): array
    {
        $names = array_keys($this->denied);
        sort($names, SORT_STRING);

        return $names;
    }
}
