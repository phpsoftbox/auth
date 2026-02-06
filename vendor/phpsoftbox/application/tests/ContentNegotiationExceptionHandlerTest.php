<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Tests;

use PhpSoftBox\Application\ErrorHandler\ContentNegotiationExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\HtmlExceptionHandler;
use PhpSoftBox\Application\ErrorHandler\JsonExceptionHandler;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\StreamFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentNegotiationExceptionHandlerTest extends TestCase
{
    /**
     * Проверяем выбор JSON-ответа при Accept: application/json.
     */
    public function testJsonAccept(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('GET', 'https://example.com/'))
            ->withHeader('Accept', 'application/json');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяем выбор JSON-ответа при X-Inertia.
     */
    public function testJsonInertiaHeader(): void
    {
        $handler = $this->makeHandler();
        $request = (new ServerRequest('GET', 'https://example.com/'))
            ->withHeader('X-Inertia', 'true');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяем выбор HTML-ответа по умолчанию.
     */
    public function testHtmlDefault(): void
    {
        $handler = $this->makeHandler();
        $request = new ServerRequest('GET', 'https://example.com/');

        $response = $handler->handle(new RuntimeException('Oops'), $request);

        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    private function makeHandler(): ContentNegotiationExceptionHandler
    {
        $responseFactory = new ResponseFactory();
        $streamFactory = new StreamFactory();

        return new ContentNegotiationExceptionHandler(
            new JsonExceptionHandler($responseFactory, $streamFactory, includeDetails: false),
            new HtmlExceptionHandler($responseFactory, $streamFactory, includeDetails: false),
        );
    }
}
