# Guard

## SessionGuard (web)

- При входе и выходе меняется session id и сбрасывается CSRF-токен (ключ `csrfTokenKey`, по умолчанию `csrf_token`):
  токен, известный до входа, после него недействителен. Новый токен `CsrfMiddleware` отдаёт в ответе на тот же запрос.
- Попытка входа несуществующего пользователя проверяет пароль по фиктивному хешу (ключ `passwordCredentialKey`,
  по умолчанию `password`): по времени ответа логины не перебрать.

```php
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\ArraySessionStore;

final readonly class User implements UserInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $passwordHash,
        public ?string $phoneNumber = null,
    ) {
    }

    public function id(): int|string|null
    {
        return $this->id;
    }
}

$users = new InMemoryUserProvider(
    users: [
        new User(1, 'admin@example.com', password_hash('secret', PASSWORD_DEFAULT)),
    ],
    credentialsMatcher: static fn (UserInterface $user, array $credentials): bool => $user instanceof User
        && $user->email === ($credentials['email'] ?? null),
    validators: [
        new PasswordCredentialsValidator(
            passwordHashResolver: static fn (UserInterface $user): ?string => $user instanceof User
                ? $user->passwordHash
                : null,
        ),
    ],
);

$session = new Session(new ArraySessionStore());
$guard = new SessionGuard($session, $users);

$guard->attempt(['email' => 'admin@example.com', 'password' => 'secret']);
```

Если нужно защититься от «старой» сессии (например, после смены пароля),
можно включить проверку хэша пользователя. В этом случае guard будет хранить хэш в
сессии и сбрасывать авторизацию при несовпадении.

```php
$guard = new SessionGuard(
    session: $session,
    users: $users,
    sessionKey: 'auth.user_id',
    sessionHashKey: 'auth.user_hash',
    userStampResolver: static fn (UserInterface $user): ?string => $user instanceof User
        ? $user->passwordHash
        : null,
);
```

## UserInterface

`UserInterface` — минимальный authenticated principal:

```php
interface UserInterface
{
    public function id(): int|string|null;
}
```

`id()` возвращает `int|string|null`, поэтому можно использовать numeric id, UUID и другие строковые идентификаторы.

Framework не знает, где в вашей модели лежат `email`, `passwordHash`, `phoneNumber` или `authStamp`.
Эти значения передаются через callbacks в provider/validator/guard configuration.

### Database user provider

`DatabaseUserProvider` гидратит `identityClass` и ожидает, что результат реализует `UserInterface`.

```php
use PhpSoftBox\Auth\Provider\DatabaseUserProvider;
use PhpSoftBox\Auth\Credentials\PasswordCredentialsValidator;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\Orm\Metadata\AttributeMetadataProvider;
use PhpSoftBox\Orm\Repository\AutoEntityMapper;
use App\Entity\User;

$mapper = new AutoEntityMapper(
    new AttributeMetadataProvider(),
    new DefaultTypeCasterFactory()->create(),
    new TypeCastOptionsManager(),
);

$provider = new DatabaseUserProvider(
    connections: $connections,
    identityClass: User::class,
    identityMapper: $mapper,
    loginFields: ['email'],
    validators: [
        new PasswordCredentialsValidator(
            passwordHashResolver: static fn (UserInterface $user): ?string => $user instanceof User
                ? $user->passwordHash
                : null,
        ),
    ],
);
```

При необходимости можно доработать результат через `userResolver`
(например, вернуть другой `UserInterface` на основе hydrated entity и raw `$row`) — аргумент опционален.

В приложении это удобно задавать в конфиге провайдера:

```php
return [
    'providers' => [
        'users' => [
            'driver' => 'database',
            'identity' => App\Entity\User::class,
        ],
    ],
];
```

## TokenGuard (api)

```php
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Token\BearerTokenExtractor;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;

$users = new InMemoryUserProvider([
    new User(1, 'admin@example.com', password_hash('secret', PASSWORD_DEFAULT)),
]);

$tokens = new ArrayTokenProvider([
    'token-123' => 1,
], $users);

$guard = new TokenGuard($tokens, new BearerTokenExtractor());
```

`TokenGuard` теперь зависит только от `TokenProviderInterface`.
Резолв пользователя выполняется внутри token-provider.
Если в token-storage хранятся только `user_id`, передайте `UserProviderInterface`
в `ArrayTokenProvider`/`DatabaseTokenProvider`.

По умолчанию `BearerTokenExtractor` принимает токен только из заголовка `Authorization`.
Если нужно разрешить токены в query-строке, укажите параметры явно:

```php
$extractor = new BearerTokenExtractor(
    headerName: 'Authorization',
    queryParams: ['access_token', 'token'],
);

$guard = new TokenGuard($tokens, $extractor);
```

## Database token lifecycle

Для bearer/API tokens используйте subject-centric `DatabaseCredentialStore` и
user-oriented `DatabaseTokenProvider`.
Raw-token не хранится в базе: клиент получает строку
формата `selector.secret`, а в таблицу `user_tokens` пишутся `selector`,
`token_hash` и `token_type = user_bearer`.

```php
use DateTimeImmutable;
use PhpSoftBox\Auth\Provider\DatabaseTokenProvider;
use PhpSoftBox\Auth\Token\DatabaseCredentialStore;

$store = new DatabaseCredentialStore($connections);
$tokens = new DatabaseTokenProvider($store, $users);

$issued = $tokens->issue(
    userId: $user->id(),
    expiresAt: new DateTimeImmutable('+30 days'),
    metadata: ['device' => 'web'],
    request: $request,
);

// Значение для Authorization header.
$rawToken = $issued->token;

// Отзыв текущего token.
$tokens->revoke($rawToken);

// Отзыв всех token пользователя.
$tokens->revokeAllForUser($user->id());
```

`DatabaseCredentialStore` не привязан к пользователю: `user_id` является только
настраиваемым storage-column, а публичный API оперирует `subjectId`. Один store
всегда настроен на конкретную пару `purpose + audience`.

`purpose` описывает назначение credential, например `user_bearer`, `api_access`
или `user_remember`. `audience` ограничивает место предъявления credential. Для
обычного browser bearer audience может быть `null`; для API его следует задавать
явно:

```php
$catalogTokens = new DatabaseCredentialStore(
    connections: $connections,
    purpose: 'api_access',
    audience: 'catalog',
);

$warehouseTokens = new DatabaseCredentialStore(
    connections: $connections,
    purpose: 'api_access',
    audience: 'warehouse',
);
```

Оба store могут использовать одну таблицу. Credential, выданный через
`$catalogTokens`, не будет найден через `$warehouseTokens`. Отсутствующий,
истёкший, отозванный credential и credential с неверным audience должны давать
одинаковый внешний ответ `401`, чтобы не раскрывать существование token.

`DatabaseCredentialStore` учитывает:

- `purpose` — отделяет user, remember, Node и integration credentials;
- `audience` — отделяет credentials разных API внутри одного purpose;
- `expires_datetime` — истёкшие token не проходят;
- `revoked_datetime` — отозванные token не проходят;
- `last_used_datetime`, `last_used_ip`, `last_used_user_agent` — обновляются при использовании token;
- `created_ip`, `created_user_agent`, `metadata` — заполняются при выдаче token.

## Remember me

Для browser remember-me используйте `DatabaseRememberTokenStore` и cookie
`remember_token`. Это не замена session-auth:
обычный web/admin guard должен сначала проверять session, а remember-token
используется только для восстановления session, если session отсутствует.

Для multi-guard приложений используйте `MultiGuardRememberService` и
`IntendedUrlStore`; они описаны отдельно в [`remember.md`](remember.md).

`DatabaseRememberTokenStore` использует тот же безопасный формат
`selector.secret`, но по умолчанию пишет в `user_tokens` с
`token_type = user_remember`.
`RememberCookieManager` формирует `Set-Cookie` с `HttpOnly`, `Secure`,
`SameSite`, path/domain и кладёт его в `CookieQueue`.

```php
use DateTimeImmutable;
use PhpSoftBox\Auth\Remember\DatabaseRememberTokenStore;
use PhpSoftBox\Auth\Remember\RememberCookieConfig;
use PhpSoftBox\Auth\Remember\RememberCookieManager;
use PhpSoftBox\Auth\Remember\RememberTokenExtractor;
use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Session\Config\CookieSecurePolicy;

$remember = new DatabaseRememberTokenStore($connections);

$issued = $remember->issue(
    userId: $user->id(),
    expiresAt: new DateTimeImmutable('+30 days'),
    metadata: ['device' => 'browser'],
    request: $request,
);

$cookies = new RememberCookieManager($cookieQueue, new RememberCookieConfig(
    domain: '.example.com',
    secure: CookieSecurePolicy::Always,
    sameSite: SameSite::Lax,
    maxAge: 60 * 60 * 24 * 30,
));

$cookies->queue($issued->token, $issued->expiresAt, $request);
$cookies->queueForget($request);

$rawToken = new RememberTokenExtractor()->extract($request);
$record = $rawToken === null ? null : $remember->findValid($rawToken, $request);
```

Для стандартного web-flow можно подключить `RememberRestoreMiddleware` после
`SessionMiddleware`: если session отсутствует, middleware прочитает
`remember_token`, проверит `DatabaseRememberTokenStore` и восстановит session
через `SessionGuard::login()`.

```php
use PhpSoftBox\Auth\Remember\RememberMismatchPolicy;
use PhpSoftBox\Auth\Remember\RememberRestoreMiddleware;

$middleware = new RememberRestoreMiddleware(
    guard: $sessionGuard,
    users: $users,
    tokens: $remember,
    extractor: new RememberTokenExtractor(),
    cookies: $cookies,
    mismatchPolicy: RememberMismatchPolicy::RevokeToken,
);
```

Если session user и remember-token user не совпадают, это подозрительное
состояние. Рекомендуемая политика: считать session primary, отозвать/удалить
remember-token и продолжить с session user либо разлогинить пользователя
полностью для sensitive areas.

По умолчанию `domain` равен `null`, то есть cookie будет host-only. Shared
domain вроде `.example.com` нужно задавать явно.

## Валидация по SMS (OTP)

`CacheOtpValidator` хранит код и попытки в PSR-16 кеше. Пока идентификатор заблокирован после исчерпания попыток,
`issue()` бросает `OtpLockedException` — новый код блокировку не снимает. PSR-16 не умеет атомарный инкремент:
параллельные запросы успевают сделать больше попыток. Чтобы лимит нельзя было обойти, передайте `attemptLimiter` с
атомарным хранилищем — попытка резервируется в нём до сравнения кода:

```php
use PhpSoftBox\Auth\Otp\CacheOtpValidator;
use PhpSoftBox\RateLimiter\RedisRateLimiter;

$otp = new CacheOtpValidator($cache, maxAttempts: 3, lockSeconds: 1800, attemptLimiter: new RedisRateLimiter($redis));
```

```php
use PhpSoftBox\Auth\Otp\InMemoryOtpValidator;
use PhpSoftBox\Auth\Credentials\OtpCredentialsValidator;
use PhpSoftBox\Auth\Provider\InMemoryUserProvider;

$otp = new InMemoryOtpValidator(maxAttempts: 5);
$otp->setCode('79001234567', '1234');

$users = new InMemoryUserProvider(
    users: [
        new User(1, 'admin@example.com', password_hash('secret', PASSWORD_DEFAULT), phoneNumber: '79001234567'),
    ],
    credentialsMatcher: static fn (UserInterface $user, array $credentials): bool => $user instanceof User
        && $user->phoneNumber === ($credentials['phone_number'] ?? null),
    validators: [
        new OtpCredentialsValidator(
            validator: $otp,
            identifierResolver: static fn (UserInterface $user): ?string => $user instanceof User
                ? $user->phoneNumber
                : null,
            credentialKey: 'sms_code',
        ),
    ],
);

$user = $users->retrieveByCredentials(['phone_number' => '79001234567', 'sms_code' => '1234']);
```
