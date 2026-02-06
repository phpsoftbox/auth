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

## UserDataInterface

Провайдеры `ArrayUserProvider` и `DatabaseUserProvider` возвращают объект
`AuthUser`, который реализует:

- `UserIdentityInterface` (метод `getId()`),
- `UserDataInterface` (метод `get($key)`).

Это упрощает работу валидаторов и политик, не привязываясь к конкретной ORM.

### Identity (DTO / Entity)

Если хочется работать не с массивом, а с DTO или Entity, можно задать
`identityResolver` при создании провайдера. Тогда `AuthUser::identity()`
вернёт объект, а метод `get()` продолжит работать по массиву атрибутов.

```php
use PhpSoftBox\Auth\Provider\DatabaseUserProvider;
use App\Entity\User;

$provider = new DatabaseUserProvider(
    connections: $connections,
    identityResolver: static fn (array $row): User => new User(
        id: (int) ($row['id'] ?? 0),
        name: (string) ($row['name'] ?? ''),
        email: $row['email'] ?? null,
        phone: (string) ($row['phone'] ?? ''),
        passwordHash: (string) ($row['password_hash'] ?? ''),
    ),
);
```

Если подключён `phpsoftbox/orm`, то можно передать `identityClass`.
ORM‑mapper автоматически преобразует строку БД в Entity без доп. запроса.

```php
$provider = new DatabaseUserProvider(
    connections: $connections,
    identityClass: User::class,
);
```

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
]);

$guard = new TokenGuard($tokens, $users, new BearerTokenExtractor());
```

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
