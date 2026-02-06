<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response extends Message implements ResponseInterface
{
    private const REASONS = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        410 => 'Gone',
        422 => 'Unprocessable Content',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        503 => 'Service Unavailable',
    ];

    private int $statusCode;
    private string $reasonPhrase;

    /**
     * @param array<string, string|string[]> $headers
     * @param resource|string|StreamInterface|null $body
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        $this->statusCode   = $this->validateStatus($status);
        $this->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::REASONS[$status] ?? '');

        $stream = $body instanceof StreamInterface ? $body : new Stream($body);

        parent::__construct($headers, $stream, $protocolVersion);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone               = clone $this;
        $clone->statusCode   = $this->validateStatus($code);
        $clone->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::REASONS[$code] ?? '');

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    private function validateStatus(int $status): int
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Status code must be between 100 and 599.');
        }

        return $status;
    }
}
