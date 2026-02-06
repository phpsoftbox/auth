<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Cookie\AuthCookieConfig;
use PhpSoftBox\Auth\Cookie\AuthCookieManager;
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\CookieSecurePolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthCookieConfig::class)]
#[CoversClass(AuthCookieManager::class)]
final class AuthCookieManagerTest extends TestCase
{
    /**
     * Проверяет, что auth-cookie выставляется с HttpOnly/SameSite/Secure и общим domain.
     */
    #[Test]
    public function queuesAuthCookieWithConfiguredSecurityOptions(): void
    {
        $queue = new CookieQueue();

        $manager = new AuthCookieManager($queue, new AuthCookieConfig(
            domain: '.example.test',
            secure: CookieSecurePolicy::Auto,
            sameSite: SameSite::Strict,
            maxAge: 3600,
        ));

        $manager->queue(
            'selector.secret',
            new DateTimeImmutable('2026-01-01 01:00:00 UTC'),
            new ServerRequest('GET', 'https://admin.example.test'),
        );

        $cookies = $queue->flush();

        self::assertCount(1, $cookies);
        $header = $cookies[0]->toHeader();

        self::assertStringContainsString('auth_token=selector.secret', $header);
        self::assertStringContainsString('Domain=.example.test', $header);
        self::assertStringContainsString('Max-Age=3600', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
    }

    /**
     * Проверяет удаление auth-cookie теми же path/domain/security настройками.
     */
    #[Test]
    public function queuesForgetCookie(): void
    {
        $queue = new CookieQueue();

        $manager = new AuthCookieManager($queue);

        $manager->queueForget(new ServerRequest('GET', 'https://example.test'));

        $cookies = $queue->flush();

        self::assertCount(1, $cookies);
        $header = $cookies[0]->toHeader();

        self::assertStringContainsString('auth_token=', $header);
        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringContainsString('HttpOnly', $header);
    }
}
