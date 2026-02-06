<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie\Tests;

use PhpSoftBox\Cookie\Cookie;
use PhpSoftBox\Cookie\CookieJar;
use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase
{
    /**
     * Проверяем парсинг Cookie-заголовка.
     */
    public function testParseHeader(): void
    {
        $cookies = Cookie::parseHeader('a=1; b=hello%20world');

        $this->assertSame('1', $cookies['a']->value);
        $this->assertSame('hello world', $cookies['b']->value);
    }

    /**
     * Проверяем формирование заголовка Set-Cookie.
     */
    public function testSetCookieHeader(): void
    {
        $cookie = SetCookie::create('sid', 'token')
            ->withSameSite(SameSite::Lax)
            ->withSecure(true)
            ->withHttpOnly(true);

        $header = $cookie->toHeader();

        $this->assertStringContainsString('sid=token', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('HttpOnly', $header);
    }

    /**
     * Проверяем работу CookieJar для Set-Cookie.
     */
    public function testCookieJarHeaders(): void
    {
        $cookie = SetCookie::create('a', '1');

        $headers = CookieJar::toHeaders([$cookie]);

        $this->assertCount(1, $headers);
        $this->assertStringContainsString('a=1', $headers[0]);
    }
}
