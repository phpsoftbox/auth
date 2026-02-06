<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Resolver;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

abstract readonly class AbstractGuardRequestUserResolver
{
    public function __construct(
        protected AuthManager $auth,
        protected ServerRequestInterface $request,
        protected string $requestUserAttribute = 'user',
    ) {
    }

    public function resolve(): ?UserInterface
    {
        $user = $this->request->getAttribute($this->requestUserAttribute);
        if ($user instanceof UserInterface) {
            return $user;
        }

        return $this->auth->guard($this->guardName())->user($this->request);
    }

    public function getId(): int|string|null
    {
        return $this->resolve()?->id();
    }

    public function getIdOrFail(): int|string
    {
        $id = $this->getId();
        if ($id === null) {
            throw new RuntimeException($this->notFoundMessage());
        }

        return $id;
    }

    abstract protected function guardName(): string;

    protected function notFoundMessage(): string
    {
        return 'User not found.';
    }
}
