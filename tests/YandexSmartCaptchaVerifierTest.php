<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Captcha\CaptchaVerifierInterface;
use PhpSoftBox\Auth\Captcha\YandexSmartCaptchaVerifier;
use PhpSoftBox\Auth\Tests\Support\RecordingHttpClient;
use PhpSoftBox\Auth\Tests\Support\TestClientException;
use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function parse_str;

#[CoversClass(YandexSmartCaptchaVerifier::class)]
final class YandexSmartCaptchaVerifierTest extends TestCase
{
    #[Test]
    public function disabledVerifierAllowsRequestWithoutHttpCall(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"failed"}'));

        $verifier = $this->verifier($client, enabled: false);

        self::assertFalse($verifier->isEnabled());
        self::assertNull($verifier->siteKey());
        self::assertTrue($verifier->verify(null, $this->request()));
        self::assertSame(0, $client->callCount);
    }

    #[Test]
    public function reportsEnabledOnlyWhenFullyConfigured(): void
    {
        self::assertTrue($this->verifier(new RecordingHttpClient())->isEnabled());
        self::assertSame('site-key', $this->verifier(new RecordingHttpClient())->siteKey());

        self::assertFalse($this->verifier(new RecordingHttpClient(), siteKey: '')->isEnabled());
        self::assertFalse($this->verifier(new RecordingHttpClient(), serverKey: '')->isEnabled());
        self::assertFalse($this->verifier(new RecordingHttpClient(), validateUrl: '')->isEnabled());
    }

    #[Test]
    public function verifiesValidTokenAndChecksRequestHost(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok","host":"tenant.example.com"}'));

        $verifier = $this->verifier($client);

        self::assertTrue($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
        self::assertNotNull($client->lastRequest);
        self::assertSame('POST', $client->lastRequest->getMethod());
        self::assertSame(YandexSmartCaptchaVerifier::DEFAULT_VALIDATE_URL, (string) $client->lastRequest->getUri());

        $payload = [];
        parse_str((string) $client->lastRequest->getBody(), $payload);
        self::assertSame('server-key', $payload['secret'] ?? null);
        self::assertSame('token-1', $payload['token'] ?? null);
        self::assertSame('203.0.113.10', $payload['ip'] ?? null);
    }

    #[Test]
    public function verifiesHostWithPortFromProviderResponse(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok","host":"tenant.example.com:8080"}'));

        $verifier = $this->verifier($client);

        self::assertTrue($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
    }

    #[Test]
    public function rejectsValidTokenFromDifferentHost(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok","host":"evil.example.com"}'));

        $verifier = $this->verifier($client);

        self::assertFalse($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
    }

    #[Test]
    public function canDisableHostValidationExplicitly(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok","host":"evil.example.com"}'));

        $verifier = $this->verifier($client, validateHost: false);

        self::assertTrue($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
    }

    #[Test]
    public function rejectsEmptyHostByDefault(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok","host":""}'));

        $verifier = $this->verifier($client);

        self::assertFalse($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
    }

    #[Test]
    public function canAllowEmptyHostExplicitly(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok","host":""}'));

        $verifier = $this->verifier($client, allowEmptyHost: true);

        self::assertTrue($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
    }

    #[Test]
    public function rejectsMissingHostFieldWhenHostValidationIsEnabled(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"ok"}'));

        $verifier = $this->verifier($client);

        self::assertFalse($verifier->verify('token-1', $this->request('https://tenant.example.com/login')));
    }

    #[Test]
    public function rejectsInvalidToken(): void
    {
        $client = new RecordingHttpClient(new Response(200, [], '{"status":"failed"}'));

        $verifier = $this->verifier($client);

        self::assertFalse($verifier->verify('token-1', $this->request()));
    }

    #[Test]
    public function rejectsWhenEnabledButKeysOrTokenAreMissing(): void
    {
        self::assertFalse($this->verifier(new RecordingHttpClient(), siteKey: '')->verify('token-1', $this->request()));
        self::assertFalse($this->verifier(new RecordingHttpClient(), serverKey: '')->verify('token-1', $this->request()));
        self::assertFalse($this->verifier(new RecordingHttpClient(), validateUrl: '')->verify('token-1', $this->request()));
        self::assertFalse($this->verifier(new RecordingHttpClient())->verify('', $this->request()));
    }

    #[Test]
    public function failOpenControlsTransportFailures(): void
    {
        $client = new RecordingHttpClient();

        $client->failWith(new TestClientException('transport failed'));

        self::assertFalse($this->verifier($client, failOpen: false)->verify('token-1', $this->request()));
        self::assertTrue($this->verifier($client, failOpen: true)->verify('token-1', $this->request()));
    }

    private function verifier(
        RecordingHttpClient $client,
        bool $enabled = true,
        string $siteKey = 'site-key',
        string $serverKey = 'server-key',
        string $validateUrl = YandexSmartCaptchaVerifier::DEFAULT_VALIDATE_URL,
        bool $failOpen = false,
        bool $validateHost = true,
        bool $allowEmptyHost = false,
    ): CaptchaVerifierInterface {
        return new YandexSmartCaptchaVerifier(
            httpClient: $client,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
            enabled: $enabled,
            siteKey: $siteKey,
            serverKey: $serverKey,
            validateUrl: $validateUrl,
            failOpen: $failOpen,
            validateHost: $validateHost,
            allowEmptyHost: $allowEmptyHost,
        );
    }

    private function request(string $uri = 'https://tenant.example.com/login'): ServerRequest
    {
        return new ServerRequest(
            method: 'POST',
            uri: $uri,
            headers: ['X-Forwarded-For' => '203.0.113.10, 10.0.0.1'],
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
        );
    }
}
