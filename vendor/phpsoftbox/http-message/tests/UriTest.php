<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Uri;
use PHPUnit\Framework\TestCase;

final class UriTest extends TestCase
{
    /**
     * Проверяем парсинг URI и корректную сборку строки.
     */
    public function testParseAndToString(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=1#frag');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/path', $uri->getPath());
        $this->assertSame('query=1', $uri->getQuery());
        $this->assertSame('frag', $uri->getFragment());
        $this->assertSame('https://user:pass@example.com:8080/path?query=1#frag', (string) $uri);
    }

    /**
     * Проверяем with* методы у Uri.
     */
    public function testWithMethods(): void
    {
        $uri = new Uri('https://example.com');

        $modified = $uri
            ->withPath('/users')
            ->withQuery('page=2')
            ->withFragment('top');

        $this->assertSame('/users', $modified->getPath());
        $this->assertSame('page=2', $modified->getQuery());
        $this->assertSame('top', $modified->getFragment());
        $this->assertSame('https://example.com/users?page=2#top', (string) $modified);
    }
}
