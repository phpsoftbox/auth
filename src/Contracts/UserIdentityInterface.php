<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

use Ramsey\Uuid\UuidInterface;

interface UserIdentityInterface
{
    public function id(): int|UuidInterface|null;
}
