# Guard

## SessionGuard (web)

```php
use PhpSoftBox\Auth\Provider\ArrayUserProvider;
use PhpSoftBox\Auth\Guard\SessionGuard;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\ArraySessionStore;

$users = new ArrayUserProvider([
    ['id' => 1, 'email' => 'admin@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
], loginFields: ['email']);

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
    userHashKey: 'password_hash',
);
```

## UserInterface

Провайдеры `ArrayUserProvider` и `DatabaseUserProvider` возвращают объект
`AuthUser`, который реализует:

- `UserInterface` (`id()`, `get()`, `identity()`).

`UserInterface::id()` возвращает `int|string|null`, поэтому можно использовать как numeric id, так и UUID/другие строковые идентификаторы.

Это упрощает работу валидаторов и политик, не привязываясь к конкретной ORM.

### Identity (DTO / Entity)

Если хочется работать не с массивом, а с DTO или Entity, передайте
`identityClass` и `identityMapper`.
Тогда `AuthUser::identity()` вернёт объект, а метод `get()` продолжит работать по массиву атрибутов.

```php
use PhpSoftBox\Auth\Provider\DatabaseUserProvider;
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
);
```

При необходимости можно доработать identity в `identityResolver`
(например, подмешать вычисляемые поля на основе `$row`) — аргумент опционален.

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
use PhpSoftBox\Auth\Guard\TokenGuard;
use PhpSoftBox\Auth\Token\BearerTokenExtractor;
use PhpSoftBox\Auth\Provider\ArrayTokenProvider;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;

$users = new ArrayUserProvider([
    ['id' => 1, 'email' => 'admin@example.com', 'password_hash' => password_hash('secret', PASSWORD_DEFAULT)],
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

Для bearer/API tokens используйте `DatabaseTokenStore` и `DatabaseTokenProvider`.
Raw-token не хранится в базе: клиент получает строку
формата `selector.secret`, а в таблицу `user_tokens` пишутся `selector`,
`token_hash` и `token_type = bearer`.

```php
use DateTimeImmutable;
use PhpSoftBox\Auth\Provider\DatabaseTokenProvider;
use PhpSoftBox\Auth\Token\DatabaseTokenStore;

$store = new DatabaseTokenStore($connections);
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

`DatabaseTokenStore` учитывает:

- `token_type` — отделяет bearer/API tokens от remember-me tokens;
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
`token_type = remember`.
`RememberCookieManager` формирует `Set-Cookie` с `HttpOnly`, `Secure`,
`SameSite`, path/domain и кладёт его в `CookieQueue`.

```php
use DateTimeImmutable;
use PhpSoftBox\Auth\Remember\DatabaseRememberTokenStore;
use PhpSoftBox\Auth\Remember\RememberCookieConfig;
use PhpSoftBox\Auth\Remember\RememberCookieManager;
use PhpSoftBox\Auth\Remember\RememberTokenExtractor;
use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Session\CookieSecurePolicy;

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

```php
use PhpSoftBox\Auth\Otp\InMemoryOtpValidator;
use PhpSoftBox\Auth\Credentials\OtpCredentialsValidator;
use PhpSoftBox\Auth\Provider\ArrayUserProvider;

$otp = new InMemoryOtpValidator(maxAttempts: 5);
$otp->setCode('79001234567', '1234');

$users = new ArrayUserProvider(
    users: [
        ['id' => 1, 'phone_number' => '79001234567'],
    ],
    loginFields: ['phone_number'],
    validators: [new OtpCredentialsValidator($otp, credentialKey: 'sms_code', identifierField: 'phone_number')],
);

$user = $users->retrieveByCredentials(['phone_number' => '79001234567', 'sms_code' => '1234']);
```
