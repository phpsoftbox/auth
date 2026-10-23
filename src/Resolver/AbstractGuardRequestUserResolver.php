<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Resolver;

use Closure;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

abstract readonly class AbstractGuardRequestUserResolver
{
    private ?Closure $requestProvider;

    /**
     * @param null|callable(): ServerRequestInterface $requestProvider
     */
    public function __construct(
        protected AuthManager $auth,
        protected ?ServerRequestInterface $request = null,
        protected string $requestUserAttribute = 'user',
        ?callable $requestProvider = null,
    ) {
        $this->requestProvider = $requestProvider !== null
            ? Closure::fromCallable($requestProvider)
            : null;
    }

    public function resolve(): ?UserInterface
    {
        $request = $this->currentRequest();
        $user    = $request->getAttribute($this->requestUserAttribute);
        if ($user instanceof UserInterface) {
            return $user;
        }

        return $this->auth->guard($this->guardName())->user($request);
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

    private function currentRequest(): ServerRequestInterface
    {
        if ($this->requestProvider !== null) {
            $request = ($this->requestProvider)();
            if ($request instanceof ServerRequestInterface) {
                return $request;
            }

            throw new RuntimeException('Request provider must return a server request.');
        }

        return $this->request ?? throw new RuntimeException('Server request is not available.');
    }
}
