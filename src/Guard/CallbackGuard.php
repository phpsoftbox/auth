<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use Psr\Http\Message\ServerRequestInterface;

final class CallbackGuard implements GuardInterface
{
    /**
     * @param callable $resolver fn(ServerRequestInterface $request): mixed
     */
    public function __construct(
        private readonly callable $resolver,
    ) {
    }

    public function user(ServerRequestInterface $request): mixed
    {
        return ($this->resolver)($request);
    }
}
