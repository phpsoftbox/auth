<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use Closure;
use PhpSoftBox\Auth\Contracts\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CallbackGuard implements GuardInterface
{
    private readonly Closure $resolver;

    /**
     * @param callable $resolver fn(ServerRequestInterface $request): UserInterface|null
     */
    public function __construct(
        callable $resolver,
    ) {
        $this->resolver = Closure::fromCallable($resolver);
    }

    public function user(ServerRequestInterface $request): ?UserInterface
    {
        return ($this->resolver)($request);
    }
}
