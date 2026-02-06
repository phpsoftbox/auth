<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

use function array_key_exists;

final class ServerRequest extends Request implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    private array $serverParams;

    /** @var array<string, mixed> */
    private array $cookieParams = [];

    /** @var array<string, mixed> */
    private array $queryParams = [];

    /** @var array<string, UploadedFileInterface> */
    private array $uploadedFiles = [];

    private mixed $parsedBody = null;

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, string|string[]> $headers
     * @param resource|string|StreamInterface|null $body
     * @param array<string, mixed> $serverParams
     * @param array<string, mixed> $cookieParams
     * @param array<string, mixed> $queryParams
     * @param array<string, UploadedFileInterface> $uploadedFiles
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = [],
        array $cookieParams = [],
        array $queryParams = [],
        array $uploadedFiles = [],
        mixed $parsedBody = null,
        array $attributes = [],
    ) {
        parent::__construct($method, $uri, $headers, $body, $protocolVersion);

        $this->serverParams  = $serverParams;
        $this->cookieParams  = $cookieParams;
        $this->queryParams   = $queryParams;
        $this->uploadedFiles = $uploadedFiles;
        $this->parsedBody    = $parsedBody;
        $this->attributes    = $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function withCookieParams(array $cookies): static
    {
        $clone               = clone $this;
        $clone->cookieParams = $cookies;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static
    {
        $clone              = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    /**
     * @return array<string, UploadedFileInterface>
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array<string, UploadedFileInterface> $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone                = clone $this;
        $clone->uploadedFiles = $uploadedFiles;

        return $clone;
    }

    public function getParsedBody(): mixed
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): static
    {
        $clone             = clone $this;
        $clone->parsedBody = $data;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): static
    {
        $clone                    = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        if (!array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }
}
