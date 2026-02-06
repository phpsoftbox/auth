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

## UserDataInterface

Провайдеры `ArrayUserProvider` и `DatabaseUserProvider` возвращают объект
`AuthUser`, который реализует:

- `UserIdentityInterface` (метод `getId()`),
- `UserDataInterface` (метод `get($key)`).

Это упрощает работу валидаторов и политик, не привязываясь к конкретной ORM.

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
