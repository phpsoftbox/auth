<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Contracts\OwnableInterface;

final class TestPost implements OwnableInterface
{
    public function __construct(
        private int $ownerId,
    ) {
    }

    public function getOwnerId(): int|string|null
    {
        return $this->ownerId;
    }
}
