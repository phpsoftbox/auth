<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use InvalidArgumentException;
use PhpSoftBox\Auth\Provider\UserProviderInterface;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;

final class SessionGuard implements GuardInterface
{
    private mixed $cachedUser         = null;
    private int|string|null $cachedId = null;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly UserProviderInterface $users,
        private readonly string $sessionKey = 'auth.user_id',
        private readonly ?string $sessionHashKey = null,
        private readonly string $userHashKey = 'password_hash',
    ) {
    }

    public function user(ServerRequestInterface $request): mixed
    {
        $id = $this->session->get($this->sessionKey);
        if ($id === null) {
            return null;
        }

        $expectedHash = null;
        if ($this->sessionHashKey !== null) {
            $expectedHash = $this->session->get($this->sessionHashKey);
            if (!is_string($expectedHash) && !is_int($expectedHash)) {
                $this->logout();

                return null;
            }
        }

        if ($this->cachedId === $id) {
            if ($expectedHash !== null) {
                $userHash = $this->resolveUserHash($this->cachedUser);
                if ($userHash === null || (string) $userHash !== (string) $expectedHash) {
                    $this->logout();

                    return null;
                }
            }

            return $this->cachedUser;
        }

        $user             = $this->users->retrieveById($id);
        $this->cachedId   = $id;
        $this->cachedUser = $user;

        if ($expectedHash !== null) {
            $userHash = $this->resolveUserHash($user);
            if ($userHash === null || (string) $userHash !== (string) $expectedHash) {
                $this->logout();

                return null;
            }
        }

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

        if ($this->sessionHashKey !== null) {
            $userHash = $this->resolveUserHash($user);
            if ($userHash === null) {
                throw new InvalidArgumentException('User hash is not resolved.');
            }

            $this->session->set($this->sessionHashKey, (string) $userHash);
        }

        $this->session->set($this->sessionKey, $id);
        $this->cachedId   = $id;
        $this->cachedUser = $user;
    }

    public function logout(): void
    {
        $this->session->forget($this->sessionKey);
        if ($this->sessionHashKey !== null) {
            $this->session->forget($this->sessionHashKey);
        }

        $this->cachedId   = null;
        $this->cachedUser = null;
    }

    private function resolveUserHash(mixed $user): string|int|null
    {
        if ($user === null) {
            return null;
        }

        if (is_array($user)) {
            $hash = $user[$this->userHashKey] ?? null;

            return is_string($hash) || is_int($hash) ? $hash : null;
        }

        if (is_object($user) && property_exists($user, $this->userHashKey)) {
            $hash = $user->{$this->userHashKey};

            return is_string($hash) || is_int($hash) ? $hash : null;
        }

        if (method_exists($user, 'get')) {
            $hash = $user->get($this->userHashKey);

            return is_string($hash) || is_int($hash) ? $hash : null;
        }

        return null;
    }
}
