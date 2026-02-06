<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Stream;
use PhpSoftBox\Http\Message\UploadedFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class UploadedFileTest extends TestCase
{
    /**
     * Проверяем, что файл сохраняется через moveTo().
     */
    public function testMoveTo(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb');
        if ($tmp === false) {
            $this->fail('Не удалось создать временный файл.');
        }

        $uploaded = new UploadedFile(new Stream('payload'), size: 7);

        $uploaded->moveTo($tmp);

        $this->assertSame('payload', file_get_contents($tmp));

        unlink($tmp);
    }

    /**
     * Проверяем, что после moveTo() доступ к stream запрещён.
     */
    public function testGetStreamAfterMoveThrows(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'psb');
        if ($tmp === false) {
            $this->fail('Не удалось создать временный файл.');
        }

        $uploaded = new UploadedFile(new Stream('payload'));

        $uploaded->moveTo($tmp);

        $this->expectException(RuntimeException::class);
        $uploaded->getStream();

        unlink($tmp);
    }
}
