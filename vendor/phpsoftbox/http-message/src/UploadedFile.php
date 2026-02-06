<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function fclose;
use function file_put_contents;
use function fopen;
use function is_resource;
use function stream_copy_to_stream;

use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_OK;

final class UploadedFile implements UploadedFileInterface
{
    private StreamInterface $stream;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private bool $moved = false;

    public function __construct(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
    ) {
        if ($error < UPLOAD_ERR_OK || $error > UPLOAD_ERR_EXTENSION) {
            throw new InvalidArgumentException('Invalid upload error status.');
        }

        $this->stream          = $stream;
        $this->size            = $size;
        $this->error           = $error;
        $this->clientFilename  = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has been moved.');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error.');
        }

        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path must not be empty.');
        }

        if ($this->moved) {
            throw new RuntimeException('Uploaded file already moved.');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error.');
        }

        $resource = $this->stream->detach();
        if (!is_resource($resource)) {
            $data = (string) $this->stream;
            if (file_put_contents($targetPath, $data) === false) {
                throw new RuntimeException('Unable to write uploaded file.');
            }
        } else {
            $target = fopen($targetPath, 'wb');
            if ($target === false) {
                throw new RuntimeException('Unable to open target file.');
            }
            stream_copy_to_stream($resource, $target);
            fclose($target);
            fclose($resource);
        }

        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
