<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface UserInterface
{
    public function id(): int|string|null;
}
