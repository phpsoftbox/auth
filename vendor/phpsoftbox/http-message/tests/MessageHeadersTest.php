<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Response;
use PHPUnit\Framework\TestCase;

final class MessageHeadersTest extends TestCase
{
    /**
     * Проверяем, что заголовки работают регистронезависимо и корректно заменяются/добавляются.
     */
    public function testHeadersCaseInsensitiveAndReplace(): void
    {
        $response = new Response(200, ['X-Test' => '1']);

        $response = $response->withAddedHeader('x-test', '2');

        $this->assertSame(['1', '2'], $response->getHeader('X-Test'));
        $this->assertSame('1,2', $response->getHeaderLine('x-test'));

        $response = $response->withHeader('X-Test', '3');

        $this->assertSame(['3'], $response->getHeader('x-test'));
        $this->assertTrue($response->hasHeader('X-TEST'));

        $response = $response->withoutHeader('X-Test');

        $this->assertFalse($response->hasHeader('x-test'));
    }
}
