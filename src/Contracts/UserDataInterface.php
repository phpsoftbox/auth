<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface UserDataInterface
{
    public function get(string $key, mixed $default = null): mixed;
}
