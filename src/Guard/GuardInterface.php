<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use PhpSoftBox\Auth\Contracts\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

interface GuardInterface
{
    public function user(ServerRequestInterface $request): ?UserInterface;
}
