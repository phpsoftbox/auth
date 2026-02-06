<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\AccountProtection;

use PhpSoftBox\Clock\Clock;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

use function explode;
use function hash;
use function http_build_query;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function max;
use function trim;

use const JSON_THROW_ON_ERROR;

final readonly class AccountProtectionService
{
    private const string DEFAULT_SCOPE = 'default';

    private int $maxAttempts;
    private int $captchaSeconds;
    private string $cachePrefix;
    private string $captchaSiteKey;
    private string $captchaSecretKey;
    private string $captchaVerifyUrl;

    /** @var array<string, AccountProtectionScopeConfig> */
    private array $scopes;

    public function __construct(
        private CacheInterface $cache,
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        AccountProtectionConfig $config,
    ) {
        $this->maxAttempts      = max(1, $config->maxAttempts);
        $this->captchaSeconds   = max(60, $config->captchaSeconds);
        $this->cachePrefix      = trim($config->cachePrefix) !== '' ? trim($config->cachePrefix) : 'auth.account_protection';
        $this->captchaSiteKey   = trim($config->captchaSiteKey);
        $this->captchaSecretKey = trim($config->captchaSecretKey);
        $this->captchaVerifyUrl = trim($config->captchaVerifyUrl) !== ''
            ? trim($config->captchaVerifyUrl)
            : 'https://smartcaptcha.yandexcloud.net/validate';
        $this->scopes = $config->scopes;
    }

    /**
     * @return array{required: bool, enabled: bool, site_key: string}
     */
    public function captchaData(ServerRequestInterface $request, string $scope): array
    {
        return [
            'required' => $this->requiresCaptcha($request, $scope),
            'enabled'  => $this->isCaptchaEnabled(),
            'site_key' => $this->captchaSiteKey,
        ];
    }

    public function requiresCaptcha(ServerRequestInterface $request, string $scope): bool
    {
        $state = $this->state($request, $scope);

        return $state['captcha_until'] > $this->now();
    }

    public function isCaptchaEnabled(): bool
    {
        return $this->captchaSiteKey !== '' && $this->captchaSecretKey !== '' && $this->captchaVerifyUrl !== '';
    }

    public function registerAttempt(ServerRequestInterface $request, string $scope): void
    {
        $key          = $this->cacheKey($request, $scope);
        $state        = $this->state($request, $scope);
        $now          = $this->now();
        $scopeConfig  = $this->resolveScope($scope);
        $attempts     = $state['attempts'] + 1;
        $captchaUntil = $state['captcha_until'];

        if ($attempts >= $scopeConfig->maxAttempts && $captchaUntil <= $now) {
            $captchaUntil = $now + $scopeConfig->captchaSeconds;
        }

        $ttl = $captchaUntil > $now ? ($captchaUntil - $now) : $scopeConfig->captchaSeconds;
        $ttl = max($ttl, 60);

        $this->cache->set($key, [
            'attempts'      => $attempts,
            'captcha_until' => $captchaUntil,
        ], $ttl);
    }

    public function registerFailure(ServerRequestInterface $request, string $scope): void
    {
        $this->registerAttempt($request, $scope);
    }

    public function reset(ServerRequestInterface $request, string $scope): void
    {
        $this->cache->delete($this->cacheKey($request, $scope));
    }

    public function validateCaptcha(ServerRequestInterface $request, string $token, string $scope): bool
    {
        if (!$this->requiresCaptcha($request, $scope)) {
            return true;
        }

        $token = trim($token);
        if ($token === '' || !$this->isCaptchaEnabled()) {
            return false;
        }

        try {
            $payload = http_build_query([
                'secret' => $this->captchaSecretKey,
                'token'  => $token,
                'ip'     => $this->resolveIp($request),
            ]);

            $httpRequest = $this->requestFactory
                ->createRequest('POST', $this->captchaVerifyUrl)
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withHeader('Accept', 'application/json')
                ->withBody($this->streamFactory->createStream($payload));

            $response = $this->client->sendRequest($httpRequest);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return false;
            }

            $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return false;
            }

            return (($decoded['status'] ?? null) === 'ok');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{attempts: int, captcha_until: int}
     */
    private function state(ServerRequestInterface $request, string $scope): array
    {
        $payload = $this->cache->get($this->cacheKey($request, $scope));
        if (!is_array($payload)) {
            return ['attempts' => 0, 'captcha_until' => 0];
        }

        $attempts = $payload['attempts'] ?? 0;
        $until    = $payload['captcha_until'] ?? 0;

        return [
            'attempts'      => is_int($attempts) ? $attempts : 0,
            'captcha_until' => is_int($until) ? $until : 0,
        ];
    }

    private function cacheKey(ServerRequestInterface $request, string $scope): string
    {
        $scope = $this->normalizeScope($scope);

        return $this->cachePrefix . '.' . hash('sha256', $scope . '|' . $this->resolveIp($request));
    }

    private function resolveScope(string $scope): AccountProtectionScopeConfig
    {
        $scope = $this->normalizeScope($scope);

        return $this->scopes[$scope] ?? new AccountProtectionScopeConfig(
            maxAttempts: $this->maxAttempts,
            captchaSeconds: $this->captchaSeconds,
        );
    }

    private function normalizeScope(string $scope): string
    {
        $scope = trim($scope);

        return $scope !== '' ? $scope : self::DEFAULT_SCOPE;
    }

    private function resolveIp(ServerRequestInterface $request): string
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

        $serverParams = $request->getServerParams();
        $remoteAddr   = $serverParams['REMOTE_ADDR'] ?? null;

        if (is_string($remoteAddr) && trim($remoteAddr) !== '') {
            return trim($remoteAddr);
        }

        return 'unknown';
    }

    private function now(): int
    {
        return Clock::now()->getTimestamp();
    }
}
