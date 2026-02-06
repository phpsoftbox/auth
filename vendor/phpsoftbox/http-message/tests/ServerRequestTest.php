<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\Uri;
use PHPUnit\Framework\TestCase;

final class ServerRequestTest extends TestCase
{
    /**
     * Проверяем работу attributes и иммутабельность ServerRequest.
     */
    public function testAttributes(): void
    {
        $request = new ServerRequest('GET', 'https://example.com/');

        $with = $request->withAttribute('id', 10);

        $this->assertNull($request->getAttribute('id'));
        $this->assertSame(10, $with->getAttribute('id'));

        $same = $request->withoutAttribute('missing');
        $this->assertSame($request, $same);
    }

    /**
     * Проверяем, что параметры конструктора сохраняются в запросе.
     */
    public function testConstructorParams(): void
    {
        $request = new ServerRequest(
            'POST',
            'https://example.com/',
            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            cookieParams: ['a' => '1'],
            queryParams: ['q' => 'x'],
            parsedBody: ['name' => 'John'],
        );

        $this->assertSame('127.0.0.1', $request->getServerParams()['REMOTE_ADDR']);
        $this->assertSame('1', $request->getCookieParams()['a']);
        $this->assertSame('x', $request->getQueryParams()['q']);
        $this->assertSame(['name' => 'John'], $request->getParsedBody());
    }

    /**
     * Проверяем обновление заголовка Host при замене Uri.
     */
    public function testHostHeaderWhenChangingUri(): void
    {
        $request = new ServerRequest('GET', 'https://example.com/');

        $this->assertSame('example.com', $request->getHeaderLine('Host'));

        $modified = $request->withUri(new Uri('/local'));

        $this->assertSame('', $modified->getHeaderLine('Host'));
    }
}
