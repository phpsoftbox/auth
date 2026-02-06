<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface UserIdentityInterface
{
    public function getId(): int|string|null;
}
