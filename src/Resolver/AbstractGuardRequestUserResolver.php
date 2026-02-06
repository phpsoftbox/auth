<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Resolver;

use PhpSoftBox\Auth\Contracts\UserIdentityInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function ctype_digit;
use function is_int;
use function is_string;

abstract readonly class AbstractGuardRequestUserResolver
{
    public function __construct(
        protected AuthManager $auth,
        protected ServerRequestInterface $request,
        protected string $requestUserAttribute = '_authUser',
    ) {
    }

    public function resolve(): mixed
    {
        $user = $this->request->getAttribute($this->requestUserAttribute);
        if ($user !== null) {
            return $user;
        }

        return $this->auth->guard($this->guardName())->user($this->request);
    }

    public function getId(): ?int
    {
        return $this->resolveUserId($this->resolve());
    }

    public function getIdOrFail(): int
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

    private function resolveUserId(mixed $user): ?int
    {
        if ($user instanceof UserIdentityInterface) {
            return $this->normalizeId($user->id());
        }

        return $this->normalizeId($this->request->getAttribute('user_id'));
    }

    private function normalizeId(mixed $id): ?int
    {
        if (is_int($id) && $id > 0) {
            return $id;
        }

        if (is_string($id) && $id !== '' && ctype_digit($id)) {
            $resolved = (int) $id;

            return $resolved > 0 ? $resolved : null;
        }

        return null;
    }
}
