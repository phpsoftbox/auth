<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Guard\GuardInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TestGuard implements GuardInterface
{
    public function __construct(
        private readonly mixed $user,
    ) {
    }

    public function user(ServerRequestInterface $request): mixed
    {
        return $this->user;
    }
}
