<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use Closure;
use Psr\Http\Message\ServerRequestInterface;

final class CallbackGuard implements GuardInterface
{
    /**
     * @param Closure $resolver fn(ServerRequestInterface $request): mixed
     */
    public function __construct(
        private readonly Closure $resolver,
    ) {
    }

    public function user(ServerRequestInterface $request): mixed
    {
        return ($this->resolver)($request);
    }
}
