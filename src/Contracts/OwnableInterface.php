<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface OwnableInterface
{
    public function getOwnerId(): int|string|null;
}
