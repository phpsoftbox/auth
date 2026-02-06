<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message\Tests;

use PhpSoftBox\Http\Message\Stream;
use PHPUnit\Framework\TestCase;

final class StreamTest extends TestCase
{
    /**
     * Проверяем базовые операции чтения и записи в Stream.
     */
    public function testReadWriteAndRewind(): void
    {
        $stream = new Stream('hello');

        $this->assertSame('hello', (string) $stream);

        $stream->rewind();
        $this->assertSame('he', $stream->read(2));

        $stream2 = new Stream();

        $stream2->write('abc');
        $stream2->rewind();

        $this->assertSame('abc', $stream2->getContents());
    }

    /**
     * Проверяем detach() и поведение после отсоединения ресурса.
     */
    public function testDetach(): void
    {
        $stream = new Stream('data');

        $resource = $stream->detach();

        $this->assertIsResource($resource);
        $this->assertNull($stream->getSize());
    }
}
