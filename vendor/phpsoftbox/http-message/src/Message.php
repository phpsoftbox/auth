<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

use function array_key_exists;
use function array_map;
use function array_merge;
use function array_values;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function preg_match;
use function strtolower;
use function trim;

abstract class Message implements MessageInterface
{
    protected string $protocolVersion = '1.1';

    /** @var array<string, string[]> */
    protected array $headers = [];

    /** @var array<string, string> */
    protected array $headerNames = [];

    protected StreamInterface $body;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(array $headers = [], ?StreamInterface $body = null, string $protocolVersion = '1.1')
    {
        $this->protocolVersion = $protocolVersion;
        $this->body            = $body ?? new Stream();
        $this->setHeaders($headers);
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone                  = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        $normalized = $this->normalizeHeaderName($name);

        return isset($this->headerNames[$normalized]);
    }

    /**
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        $normalized = $this->normalizeHeaderName($name);
        $key        = $this->headerNames[$normalized] ?? null;

        if ($key === null) {
            return [];
        }

        return $this->headers[$key];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);

        return implode(',', $values);
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, true);

        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->setHeader($name, $value, false);

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone      = clone $this;
        $normalized = $this->normalizeHeaderName($name);
        $key        = $clone->headerNames[$normalized] ?? null;
        if ($key === null) {
            return $clone;
        }

        unset($clone->headers[$key], $clone->headerNames[$normalized]);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone       = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, $value, true);
        }
    }

    private function setHeader(string $name, $value, bool $replace): void
    {
        $normalized = $this->normalizeHeaderName($name);
        $values     = $this->normalizeHeaderValue($value);
        $key        = $this->headerNames[$normalized] ?? $name;

        if ($replace || !array_key_exists($key, $this->headers)) {
            $this->headers[$key] = $values;
        } else {
            $this->headers[$key] = array_values(array_merge($this->headers[$key], $values));
        }

        $this->headerNames[$normalized] = $key;
    }

    private function normalizeHeaderName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Header name must not be empty.');
        }

        if (preg_match('/[^a-zA-Z0-9\-\_]/', $name) === 1) {
            throw new InvalidArgumentException('Header name contains invalid characters.');
        }

        return strtolower($name);
    }

    /**
     * @return string[]
     */
    private function normalizeHeaderValue($value): array
    {
        if (is_array($value)) {
            return array_map(static fn ($v) => (string) $v, $value);
        }

        if (!is_string($value) && $value !== null && !is_numeric($value)) {
            throw new InvalidArgumentException('Header value must be a string or array of strings.');
        }

        return [(string) $value];
    }
}
