<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\RequestFactory;
use PhpSoftBox\Http\Message\ResponseFactory;
use PhpSoftBox\Http\Message\ServerRequestFactory;
use PhpSoftBox\Http\Message\StreamFactory;
use PhpSoftBox\Http\Message\UploadedFileFactory;
use PhpSoftBox\Http\Message\UriFactory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class FactoriesTest extends TestCase
{
    /**
     * Проверяем, что фабрики создают корректные объекты PSR-7.
     */
    public function testFactories(): void
    {
        $requestFactory       = new RequestFactory();
        $serverRequestFactory = new ServerRequestFactory();
        $responseFactory      = new ResponseFactory();
        $streamFactory        = new StreamFactory();
        $uriFactory           = new UriFactory();
        $uploadedFileFactory  = new UploadedFileFactory();

        $uri           = $uriFactory->createUri('https://example.com');
        $request       = $requestFactory->createRequest('GET', $uri);
        $serverRequest = $serverRequestFactory->createServerRequest('POST', $uri, ['REMOTE_ADDR' => '127.0.0.1']);
        $response      = $responseFactory->createResponse(201);

        $stream   = $streamFactory->createStream('body');
        $uploaded = $uploadedFileFactory->createUploadedFile($stream, 4);

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('POST', $serverRequest->getMethod());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(4, $uploaded->getSize());
    }

    /**
     * Проверяем создание Stream из файла.
     */
    public function testCreateStreamFromFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb');
        if ($tmp === false) {
            $this->fail('Не удалось создать временный файл.');
        }

        file_put_contents($tmp, 'file');

        $factory = new StreamFactory();

        $stream = $factory->createStreamFromFile($tmp);

        $this->assertSame('file', $stream->getContents());

        unlink($tmp);
    }
}
