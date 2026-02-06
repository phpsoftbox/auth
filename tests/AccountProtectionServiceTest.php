<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\AccountProtection\AccountProtectionConfig;
use PhpSoftBox\Auth\AccountProtection\AccountProtectionScopeConfig;
use PhpSoftBox\Auth\AccountProtection\AccountProtectionService;
use PhpSoftBox\Auth\Tests\Support\RecordingHttpClient;
use PhpSoftBox\Auth\Tests\Support\TestArrayCache;
use PhpSoftBox\Auth\Tests\Support\TestClientException;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function parse_str;

#[CoversClass(AccountProtectionConfig::class)]
#[CoversClass(AccountProtectionScopeConfig::class)]
#[CoversClass(AccountProtectionService::class)]
final class AccountProtectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Clock::freeze(new DateTimeImmutable('2026-03-04 12:00:00'));
    }

    protected function tearDown(): void
    {
        Clock::reset();

        parent::tearDown();
    }

    /**
     * Проверяет пошаговый сценарий:
     * 1) до порога CAPTCHA не требуется;
     * 2) на пороге CAPTCHA становится обязательной;
     * 3) reset() очищает состояние.
     *
     * @see AccountProtectionService::registerFailure()
     * @see AccountProtectionService::requiresCaptcha()
     * @see AccountProtectionService::reset()
     */
    #[Test]
    public function captchaTurnsOnAfterThresholdAndResetsAfterSuccess(): void
    {
        $request = $this->request();
        $service = $this->createService(config: new AccountProtectionConfig(
            maxAttempts: 4,
            captchaSeconds: 120,
            scopes: [
                'admin.login' => new AccountProtectionScopeConfig(maxAttempts: 4, captchaSeconds: 120),
            ],
        ));

        self::assertFalse($service->requiresCaptcha($request, 'admin.login'));

        $service->registerFailure($request, 'admin.login');
        $service->registerFailure($request, 'admin.login');
        $service->registerFailure($request, 'admin.login');
        self::assertFalse($service->requiresCaptcha($request, 'admin.login'));

        $service->registerFailure($request, 'admin.login');
        self::assertTrue($service->requiresCaptcha($request, 'admin.login'));

        $service->reset($request, 'admin.login');
        self::assertFalse($service->requiresCaptcha($request, 'admin.login'));
    }

    /**
     * Проверяет, что CAPTCHA действует только в заданном временном окне.
     *
     * @see AccountProtectionService::requiresCaptcha()
     * @see AccountProtectionService::registerFailure()
     */
    #[Test]
    public function captchaIsRequiredOnlyWithinConfiguredDuration(): void
    {
        $request = $this->request();
        $service = $this->createService(config: new AccountProtectionConfig(
            maxAttempts: 1,
            captchaSeconds: 120,
            scopes: [
                'admin.login' => new AccountProtectionScopeConfig(maxAttempts: 1, captchaSeconds: 120),
            ],
        ));

        $service->registerFailure($request, 'admin.login');
        self::assertTrue($service->requiresCaptcha($request, 'admin.login'));

        Clock::travel(119);
        self::assertTrue($service->requiresCaptcha($request, 'admin.login'));

        Clock::travel(1);
        self::assertFalse($service->requiresCaptcha($request, 'admin.login'));
    }

    /**
     * Проверяет инкремент счетчика и повторный запуск окна CAPTCHA:
     * если попытки продолжаются во время обязательной CAPTCHA,
     * после истечения окна следующая неудача снова включает CAPTCHA.
     *
     * @see AccountProtectionService::registerFailure()
     * @see AccountProtectionService::requiresCaptcha()
     */
    #[Test]
    public function failuresContinueCountingAndCanStartNewCaptchaWindow(): void
    {
        $request = $this->request();
        $service = $this->createService(config: new AccountProtectionConfig(
            maxAttempts: 3,
            captchaSeconds: 120,
            scopes: [
                'cabinet.login' => new AccountProtectionScopeConfig(maxAttempts: 3, captchaSeconds: 120),
            ],
        ));

        $service->registerFailure($request, 'cabinet.login');
        $service->registerFailure($request, 'cabinet.login');
        $service->registerFailure($request, 'cabinet.login');
        self::assertTrue($service->requiresCaptcha($request, 'cabinet.login'));

        Clock::travel(119);
        $service->registerFailure($request, 'cabinet.login');
        self::assertTrue($service->requiresCaptcha($request, 'cabinet.login'));

        Clock::travel(2);
        self::assertFalse($service->requiresCaptcha($request, 'cabinet.login'));

        $service->registerFailure($request, 'cabinet.login');
        self::assertTrue($service->requiresCaptcha($request, 'cabinet.login'));
    }

    /**
     * Проверяет, что разные scope изолированы (ключи и состояние не пересекаются).
     *
     * @see AccountProtectionService::registerFailure()
     * @see AccountProtectionService::requiresCaptcha()
     */
    #[Test]
    public function scopesAreIndependent(): void
    {
        $request = $this->request();
        $service = $this->createService(config: new AccountProtectionConfig(
            maxAttempts: 2,
            captchaSeconds: 120,
            scopes: [
                'admin.login'   => new AccountProtectionScopeConfig(maxAttempts: 2, captchaSeconds: 120),
                'cabinet.login' => new AccountProtectionScopeConfig(maxAttempts: 3, captchaSeconds: 120),
            ],
        ));

        $service->registerFailure($request, 'admin.login');
        $service->registerFailure($request, 'admin.login');

        self::assertTrue($service->requiresCaptcha($request, 'admin.login'));
        self::assertFalse($service->requiresCaptcha($request, 'cabinet.login'));
    }

    /**
     * Проверяет структуру `captchaData()`:
     * флаг required, enabled и site_key.
     *
     * @see AccountProtectionService::captchaData()
     */
    #[Test]
    public function captchaDataReturnsExpectedPayload(): void
    {
        $request = $this->request();
        $service = $this->createService(config: new AccountProtectionConfig(
            maxAttempts: 1,
            captchaSeconds: 120,
            captchaSiteKey: 'site-key',
            captchaSecretKey: 'secret-key',
            captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
            scopes: [
                'admin.login' => new AccountProtectionScopeConfig(maxAttempts: 1, captchaSeconds: 120),
            ],
        ));

        $initial = $service->captchaData($request, 'admin.login');
        self::assertSame([
            'required' => false,
            'enabled'  => true,
            'site_key' => 'site-key',
        ], $initial);

        $service->registerFailure($request, 'admin.login');
        $afterFailure = $service->captchaData($request, 'admin.login');
        self::assertTrue($afterFailure['required']);
        self::assertTrue($afterFailure['enabled']);
        self::assertSame('site-key', $afterFailure['site_key']);
    }

    /**
     * Проверяет, что при неактивной CAPTCHA `validateCaptcha()` не делает HTTP-запрос.
     *
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaSkipsHttpWhenCaptchaNotRequired(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok"}'));

        $service = $this->createService(
            client: $client,
            config: new AccountProtectionConfig(maxAttempts: 3, captchaSeconds: 120),
        );

        self::assertTrue($service->validateCaptcha($this->request(), 'token-value', 'admin.login'));
        self::assertSame(0, $client->callCount);
    }

    /**
     * Проверяет отказ валидации при обязательной CAPTCHA и пустом токене.
     *
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaFailsWhenTokenIsMissing(): void
    {
        $request = $this->request();
        $client  = new RecordingHttpClient(new Response(200, [], '{"status":"ok"}'));

        $service = $this->createService(
            client: $client,
            config: new AccountProtectionConfig(
                maxAttempts: 1,
                captchaSeconds: 120,
                captchaSiteKey: 'site-key',
                captchaSecretKey: 'secret-key',
                captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
            ),
        );

        $service->registerFailure($request, 'admin.login');
        self::assertFalse($service->validateCaptcha($request, '', 'admin.login'));
        self::assertSame(0, $client->callCount);
    }

    /**
     * Проверяет отказ валидации, если CAPTCHA обязательна, но не настроена.
     *
     * @see AccountProtectionService::isCaptchaEnabled()
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaFailsWhenCaptchaIsNotConfigured(): void
    {
        $request = $this->request();
        $service = $this->createService(config: new AccountProtectionConfig(
            maxAttempts: 1,
            captchaSeconds: 120,
        ));

        $service->registerFailure($request, 'admin.login');
        self::assertFalse($service->isCaptchaEnabled());
        self::assertFalse($service->validateCaptcha($request, 'token-value', 'admin.login'));
    }

    /**
     * Проверяет успешную валидацию:
     * - отправка на verify endpoint,
     * - корректный body payload,
     * - выбор первого IP из X-Forwarded-For.
     *
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaSendsExpectedPayloadAndUsesForwardedIp(): void
    {
        $request = $this->request(headers: ['X-Forwarded-For' => '10.20.30.40, 10.0.0.1']);
        $client  = new RecordingHttpClient(new Response(200, [], '{"status":"ok"}'));

        $service = $this->createService(
            client: $client,
            config: new AccountProtectionConfig(
                maxAttempts: 1,
                captchaSeconds: 120,
                captchaSiteKey: 'site-key',
                captchaSecretKey: 'secret-key',
                captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
            ),
        );

        $service->registerFailure($request, 'admin.login');
        self::assertTrue($service->validateCaptcha($request, 'token-123', 'admin.login'));

        self::assertNotNull($client->lastRequest);
        self::assertSame('POST', $client->lastRequest->getMethod());
        self::assertSame('https://smartcaptcha.yandexcloud.net/validate', (string) $client->lastRequest->getUri());

        $payload = [];
        parse_str((string) $client->lastRequest->getBody(), $payload);
        self::assertSame('secret-key', $payload['secret'] ?? null);
        self::assertSame('token-123', $payload['token'] ?? null);
        self::assertSame('10.20.30.40', $payload['ip'] ?? null);
    }

    /**
     * Проверяет отказ валидации на неуспешном HTTP-статусе verify endpoint.
     *
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaFailsOnNonSuccessHttpStatus(): void
    {
        $request = $this->request();
        $client  = new RecordingHttpClient(new Response(500, [], '{"status":"ok"}'));

        $service = $this->createService(
            client: $client,
            config: new AccountProtectionConfig(
                maxAttempts: 1,
                captchaSeconds: 120,
                captchaSiteKey: 'site-key',
                captchaSecretKey: 'secret-key',
                captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
            ),
        );

        $service->registerFailure($request, 'admin.login');
        self::assertFalse($service->validateCaptcha($request, 'token-123', 'admin.login'));
    }

    /**
     * Проверяет отказ валидации на некорректном JSON-ответе verify endpoint.
     *
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaFailsOnInvalidJsonResponse(): void
    {
        $request = $this->request();
        $client  = new RecordingHttpClient(new Response(200, [], 'not-json'));

        $service = $this->createService(
            client: $client,
            config: new AccountProtectionConfig(
                maxAttempts: 1,
                captchaSeconds: 120,
                captchaSiteKey: 'site-key',
                captchaSecretKey: 'secret-key',
                captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
            ),
        );

        $service->registerFailure($request, 'admin.login');
        self::assertFalse($service->validateCaptcha($request, 'token-123', 'admin.login'));
    }

    /**
     * Проверяет отказ валидации при исключении HTTP-клиента.
     *
     * @see AccountProtectionService::validateCaptcha()
     */
    #[Test]
    public function validateCaptchaFailsWhenHttpClientThrowsException(): void
    {
        $request = $this->request();
        $client  = new RecordingHttpClient(new Response(200, [], '{"status":"ok"}'))
            ->failWith(new TestClientException('transport failed'));

        $service = $this->createService(
            client: $client,
            config: new AccountProtectionConfig(
                maxAttempts: 1,
                captchaSeconds: 120,
                captchaSiteKey: 'site-key',
                captchaSecretKey: 'secret-key',
                captchaVerifyUrl: 'https://smartcaptcha.yandexcloud.net/validate',
            ),
        );

        $service->registerFailure($request, 'admin.login');
        self::assertFalse($service->validateCaptcha($request, 'token-123', 'admin.login'));
    }

    private function createService(
        ?RecordingHttpClient $client = null,
        ?AccountProtectionConfig $config = null,
    ): AccountProtectionService {
        return new AccountProtectionService(
            cache: new TestArrayCache(),
            client: $client ?? new RecordingHttpClient(new Response(200, [], '{"status":"ok"}')),
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
            config: $config ?? new AccountProtectionConfig(),
        );
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed> $serverParams
     */
    private function request(array $headers = [], array $serverParams = ['REMOTE_ADDR' => '127.0.0.1']): ServerRequest
    {
        return new ServerRequest(
            method: 'POST',
            uri: 'https://example.com/auth',
            headers: $headers,
            serverParams: $serverParams,
        );
    }
}
