<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Captcha;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

use function explode;
use function hash_equals;
use function http_build_query;
use function is_array;
use function is_string;
use function json_decode;
use function parse_url;
use function str_contains;
use function strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;
use const PHP_URL_HOST;

final readonly class YandexSmartCaptchaVerifier implements CaptchaVerifierInterface
{
    public const string DEFAULT_VALIDATE_URL = 'https://smartcaptcha.cloud.yandex.ru/validate';

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private bool $enabled,
        private string $siteKey,
        private string $serverKey,
        private string $validateUrl = self::DEFAULT_VALIDATE_URL,
        private bool $failOpen = false,
        private bool $validateHost = true,
        private bool $allowEmptyHost = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled
            && trim($this->siteKey) !== ''
            && trim($this->serverKey) !== ''
            && trim($this->validateUrl) !== '';
    }

    public function siteKey(): ?string
    {
        $siteKey = trim($this->siteKey);

        return $this->isEnabled() ? $siteKey : null;
    }

    public function verify(?string $token, ServerRequestInterface $request): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $siteKey   = trim($this->siteKey);
        $serverKey = trim($this->serverKey);
        $verifyUrl = trim($this->validateUrl);
        $token     = trim((string) $token);
        if ($siteKey === '' || $serverKey === '' || $verifyUrl === '' || $token === '') {
            return false;
        }

        $body = http_build_query([
            'secret' => $serverKey,
            'token'  => $token,
            'ip'     => $this->clientIp($request),
        ]);

        $httpRequest = $this->requestFactory
            ->createRequest('POST', $verifyUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface) {
            return $this->failOpen;
        } catch (Throwable) {
            return $this->failOpen;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return $this->failOpen;
        }

        try {
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (!is_array($payload) || ($payload['status'] ?? null) !== 'ok') {
            return false;
        }

        return $this->hostMatches($payload, $request);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hostMatches(array $payload, ServerRequestInterface $request): bool
    {
        if (!$this->validateHost) {
            return true;
        }

        $responseHost = $payload['host'] ?? null;
        if (!is_string($responseHost)) {
            return false;
        }

        $responseHost = $this->normalizeHost($responseHost);
        if ($responseHost === '') {
            return $this->allowEmptyHost;
        }

        $requestHost = $this->normalizeHost($request->getUri()->getHost());

        return $requestHost !== '' && hash_equals($requestHost, $responseHost);
    }

    private function normalizeHost(string $host): string
    {
        $host = trim(strtolower($host));
        $host = trim($host, '.');
        if ($host === '') {
            return '';
        }

        $parsed = str_contains($host, '://')
            ? parse_url($host, PHP_URL_HOST)
            : parse_url('//' . $host, PHP_URL_HOST);

        if (is_string($parsed) && trim($parsed) !== '') {
            $host = trim(strtolower($parsed));
        }

        return trim($host, '.');
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $forwardedFor = trim($request->getHeaderLine('X-Forwarded-For'));
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $first = trim((string) ($parts[0] ?? ''));
            if ($first !== '') {
                return $first;
            }
        }

        $realIp = trim($request->getHeaderLine('X-Real-Ip'));
        if ($realIp !== '') {
            return $realIp;
        }

        $server = $request->getServerParams();
        $ip     = $server['REMOTE_ADDR'] ?? '';

        return is_string($ip) ? trim($ip) : '';
    }
}
