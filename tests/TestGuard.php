<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\GuardInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TestGuard implements GuardInterface
{
    public function __construct(
        private readonly ?UserInterface $user,
    ) {
    }

    public function user(ServerRequestInterface $request): ?UserInterface
    {
        return $this->user;
    }
}
