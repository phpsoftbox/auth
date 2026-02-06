<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Remember\RememberTokenExtractor;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RememberTokenExtractor::class)]
final class RememberTokenExtractorTest extends TestCase
{
    /**
     * Проверяет чтение remember-token из cookie params запроса.
     */
    #[Test]
    public function extractsTokenFromCookieParams(): void
    {
        $request = new ServerRequest('GET', 'https://example.test')
            ->withCookieParams(['remember_token' => 'selector.secret']);

        self::assertSame('selector.secret', new RememberTokenExtractor()->extract($request));
    }

    /**
     * Проверяет fallback на raw Cookie header.
     */
    #[Test]
    public function extractsTokenFromCookieHeader(): void
    {
        $request = new ServerRequest('GET', 'https://example.test', [
            'Cookie' => 'theme=dark; remember_token=selector.secret',
        ]);

        self::assertSame('selector.secret', new RememberTokenExtractor()->extract($request));
    }

    /**
     * Проверяет, что пустая remember-cookie не считается токеном.
     */
    #[Test]
    public function ignoresEmptyToken(): void
    {
        $request = new ServerRequest('GET', 'https://example.test')
            ->withCookieParams(['remember_token' => '']);

        self::assertNull(new RememberTokenExtractor()->extract($request));
    }
}
