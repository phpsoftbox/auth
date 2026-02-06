<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\ServerRequestCreator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const UPLOAD_ERR_OK;

final class ServerRequestCreatorTest extends TestCase
{
    /**
     * Проверяем сборку ServerRequest из глобальных массивов.
     */
    public function testFromGlobals(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb');
        if ($tmp === false) {
            $this->fail('Не удалось создать временный файл.');
        }

        file_put_contents($tmp, 'file');

        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/users/10?x=1',
            'SERVER_NAME'    => 'example.com',
            'SERVER_PORT'    => '8080',
            'HTTPS'          => 'on',
            'HTTP_X_TEST'    => 'ok',
            'CONTENT_TYPE'   => 'application/json',
        ];

        $query   = ['x' => '1'];
        $body    = ['name' => 'John'];
        $cookies = ['sid' => 'abc'];
        $files   = [
            'avatar' => [
                'tmp_name' => $tmp,
                'size'     => 4,
                'error'    => UPLOAD_ERR_OK,
                'name'     => 'a.txt',
                'type'     => 'text/plain',
            ],
        ];

        $creator = new ServerRequestCreator();

        $request = $creator->fromGlobals($server, $query, $body, $cookies, $files);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https', $request->getUri()->getScheme());
        $this->assertSame('example.com', $request->getUri()->getHost());
        $this->assertSame(8080, $request->getUri()->getPort());
        $this->assertSame('/users/10', $request->getUri()->getPath());
        $this->assertSame('x=1', $request->getUri()->getQuery());

        $this->assertSame('ok', $request->getHeaderLine('X-Test'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $this->assertSame($query, $request->getQueryParams());
        $this->assertSame($body, $request->getParsedBody());
        $this->assertSame($cookies, $request->getCookieParams());

        $uploaded = $request->getUploadedFiles()['avatar'] ?? null;
        $this->assertInstanceOf(UploadedFileInterface::class, $uploaded);

        unlink($tmp);
    }
}
