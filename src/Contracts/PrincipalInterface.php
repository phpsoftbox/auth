<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface PrincipalInterface
{
    public function id(): int|string|null;
}
