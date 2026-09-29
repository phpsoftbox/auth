<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use Closure;
use InvalidArgumentException;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Provider\UserProviderInterface;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function bin2hex;
use function is_int;
use function is_string;
use function password_hash;
use function password_verify;
use function random_bytes;

use const PASSWORD_DEFAULT;

/**
 * Вход через сессию.
 *
 * При входе и выходе меняются session id и CSRF-токен (ключ `$csrfTokenKey`): токен, известный до входа (фиксация
 * сессии), после него недействителен. Попытка входа несуществующего пользователя выполняет ту же проверку пароля, что
 * и для существующего, — по времени ответа нельзя перебрать логины.
 */
final class SessionGuard implements GuardInterface
{
    /**
     * Хеш для проверки пароля, когда пользователь не найден: время ответа такое же, как при неверном пароле.
     */
    private static ?string $dummyPasswordHash = null;

    private ?UserInterface $cachedUser = null;
    private int|string|null $cachedId  = null;
    private readonly ?Closure $userStampResolver;

    public function __construct(
        private readonly SessionInterface $session,
        private readonly UserProviderInterface $users,
        private readonly string $sessionKey = 'auth.user_id',
        private readonly ?string $sessionHashKey = null,
        ?callable $userStampResolver = null,
        private readonly ?string $csrfTokenKey = 'csrf_token',
        private readonly string $passwordCredentialKey = 'password',
    ) {
        $this->userStampResolver = $userStampResolver === null ? null : Closure::fromCallable($userStampResolver);
        if ($this->sessionHashKey !== null && $this->userStampResolver === null) {
            throw new InvalidArgumentException('User stamp resolver is required when session hash key is configured.');
        }
    }

    public function user(ServerRequestInterface $request): ?UserInterface
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
                $userHash = $this->resolveUserStamp($this->cachedUser);
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
            $userHash = $this->resolveUserStamp($user);
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
            $this->equalizePasswordTiming($credentials);

            return false;
        }

        if (!$this->users->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(UserInterface $user): void
    {
        $id = $user->id();
        if ($id === null) {
            throw new InvalidArgumentException('User identifier is not resolved.');
        }

        $this->session->regenerate();
        $this->forgetCsrfToken();

        if ($this->sessionHashKey !== null) {
            $userHash = $this->resolveUserStamp($user);
            if ($userHash === null) {
                throw new InvalidArgumentException('User stamp is not resolved.');
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

        $this->session->regenerate();
        $this->forgetCsrfToken();

        $this->cachedId   = null;
        $this->cachedUser = null;
    }

    /**
     * Новый токен выдаст CsrfMiddleware в ответе на этот же запрос.
     */
    private function forgetCsrfToken(): void
    {
        if ($this->csrfTokenKey !== null) {
            $this->session->forget($this->csrfTokenKey);
        }
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function equalizePasswordTiming(array $credentials): void
    {
        $password = $credentials[$this->passwordCredentialKey] ?? null;
        if (!is_string($password)) {
            return;
        }

        self::$dummyPasswordHash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        password_verify($password, self::$dummyPasswordHash);
    }

    private function resolveUserStamp(?UserInterface $user): string|int|null
    {
        if ($user === null || $this->userStampResolver === null) {
            return null;
        }

        $stamp = ($this->userStampResolver)($user);

        return is_string($stamp) || is_int($stamp) ? $stamp : null;
    }
}
