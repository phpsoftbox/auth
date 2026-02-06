<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use InvalidArgumentException;
use PhpSoftBox\Auth\Provider\UserProviderInterface;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SessionGuard implements GuardInterface
{
    private mixed $cachedUser         = null;
    private int|string|null $cachedId = null;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly UserProviderInterface $users,
        private readonly string $sessionKey = 'auth.user_id',
    ) {
    }

    public function user(ServerRequestInterface $request): mixed
    {
        $id = $this->session->get($this->sessionKey);
        if ($id === null) {
            return null;
        }

        if ($this->cachedId === $id) {
            return $this->cachedUser;
        }

        $user             = $this->users->retrieveById($id);
        $this->cachedId   = $id;
        $this->cachedUser = $user;

        return $user;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials): bool
    {
        $user = $this->users->retrieveByCredentials($credentials);
        if ($user === null) {
            return false;
        }

        if (!$this->users->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(mixed $user): void
    {
        $id = $this->users->getUserId($user);
        if ($id === null) {
            throw new InvalidArgumentException('User identifier is not resolved.');
        }

        $this->session->set($this->sessionKey, $id);
        $this->cachedId   = $id;
        $this->cachedUser = $user;
    }

    public function logout(): void
    {
        $this->session->forget($this->sessionKey);
        $this->cachedId   = null;
        $this->cachedUser = null;
    }
}
