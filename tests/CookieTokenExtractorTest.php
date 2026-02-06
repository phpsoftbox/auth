<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Token\CookieTokenExtractor;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieTokenExtractor::class)]
final class CookieTokenExtractorTest extends TestCase
{
    /**
     * Проверяет чтение auth-token из cookie params запроса.
     */
    #[Test]
    public function extractsTokenFromCookieParams(): void
    {
        $request = new ServerRequest('GET', 'https://example.test')
            ->withCookieParams(['auth_token' => 'token-123']);

        self::assertSame('token-123', new CookieTokenExtractor()->extract($request));
    }

    /**
     * Проверяет fallback на raw Cookie header, если cookie params ещё не заполнены middleware.
     */
    #[Test]
    public function extractsTokenFromCookieHeader(): void
    {
        $request = new ServerRequest('GET', 'https://example.test', [
            'Cookie' => 'theme=dark; auth_token=selector.secret',
        ]);

        self::assertSame('selector.secret', new CookieTokenExtractor()->extract($request));
    }

    /**
     * Проверяет, что пустая cookie не считается токеном.
     */
    #[Test]
    public function ignoresEmptyToken(): void
    {
        $request = new ServerRequest('GET', 'https://example.test')
            ->withCookieParams(['auth_token' => '']);

        self::assertNull(new CookieTokenExtractor()->extract($request));
    }
}
