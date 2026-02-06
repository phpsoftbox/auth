<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function fwrite;
use function is_resource;
use function is_string;
use function stream_get_contents;
use function stream_get_meta_data;
use function strpbrk;

use const SEEK_SET;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    private ?int $size = null;

    /**
     * @param resource|string|null $stream
     */
    public function __construct(mixed $stream = null)
    {
        if ($stream === null) {
            $stream = fopen('php://temp', 'r+');
        }

        if (is_string($stream)) {
            $resource = fopen('php://temp', 'r+');
            if ($resource === false) {
                throw new RuntimeException('Unable to create stream.');
            }
            fwrite($resource, $stream);
            fseek($resource, 0);
            $stream = $resource;
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource, string, or null.');
        }

        $this->resource = $stream;
    }

    public function __toString(): string
    {
        try {
            if ($this->resource === null) {
                return '';
            }

            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->resource = null;
            $this->size     = null;
        }
    }

    public function detach(): mixed
    {
        $resource       = $this->resource;
        $this->resource = null;
        $this->size     = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        if ($this->size !== null) {
            return $this->size;
        }

        $stats = fstat($this->resource);
        if ($stats === false) {
            return null;
        }

        $this->size = $stats['size'] ?? null;

        return $this->size;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $result = ftell($this->resource);
        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position.');
        }

        return $result;
    }

    public function eof(): bool
    {
        return $this->resource === null ? true : feof($this->resource);
    }

    public function isSeekable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);

        return (bool) ($meta['seekable'] ?? false);
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }

        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek in stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'] ?? '';

        return strpbrk($mode, 'wca+') !== false;
    }

    public function write(string $string): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }

        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }

        $this->size = null;

        $written = fwrite($this->resource, $string);
        if ($written === false) {
            throw new RuntimeException('Unable to write to stream.');
        }

        return $written;
    }

    public function isReadable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'] ?? '';

        return strpbrk($mode, 'r+') !== false;
    }

    public function read(int $length): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }

        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }

        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('Unable to read from stream.');
        }

        return $data;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $meta = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
