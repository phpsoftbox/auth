<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use DateTimeImmutable;
use PhpSoftBox\Auth\Remember\RememberCookieConfig;
use PhpSoftBox\Auth\Remember\RememberCookieManager;
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Config\CookieSecurePolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RememberCookieConfig::class)]
#[CoversClass(RememberCookieManager::class)]
final class RememberCookieManagerTest extends TestCase
{
    /**
     * Проверяет, что remember-cookie выставляется с HttpOnly/SameSite/Secure и общим domain.
     */
    #[Test]
    public function queuesRememberCookieWithConfiguredSecurityOptions(): void
    {
        $queue = new CookieQueue();

        $manager = new RememberCookieManager($queue, new RememberCookieConfig(
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

        self::assertStringContainsString('remember_token=selector.secret', $header);
        self::assertStringContainsString('Domain=.example.test', $header);
        self::assertStringContainsString('Max-Age=3600', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
    }

    /**
     * Проверяет удаление remember-cookie теми же path/domain/security настройками.
     */
    #[Test]
    public function queuesForgetCookie(): void
    {
        $queue = new CookieQueue();

        $manager = new RememberCookieManager($queue);

        $manager->queueForget(new ServerRequest('GET', 'https://example.test'));

        $cookies = $queue->flush();

        self::assertCount(1, $cookies);
        $header = $cookies[0]->toHeader();

        self::assertStringContainsString('remember_token=', $header);
        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringContainsString('HttpOnly', $header);
    }
}
