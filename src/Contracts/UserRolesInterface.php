<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface UserRolesInterface
{
    /**
     * @return list<string>
     */
    public function getRoleNames(): array;
}
