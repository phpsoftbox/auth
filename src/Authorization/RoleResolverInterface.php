<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

interface RoleResolverInterface
{
    /**
     * @return list<string>
     */
    public function resolve(mixed $user): array;
}
