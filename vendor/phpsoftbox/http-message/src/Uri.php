<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

use function ltrim;
use function parse_url;
use function strtolower;

final class Uri implements UriInterface
{
    private string $scheme   = '';
    private string $userInfo = '';
    private string $host     = '';
    private ?int $port       = null;
    private string $path     = '';
    private string $query    = '';
    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            throw new InvalidArgumentException('Invalid URI string.');
        }

        $this->scheme   = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->host     = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port     = isset($parts['port']) ? (int) $parts['port'] : null;
        $this->path     = $parts['path'] ?? '';
        $this->query    = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';

        $user           = $parts['user'] ?? '';
        $pass           = $parts['pass'] ?? '';
        $this->userInfo = $user;
        if ($pass !== '') {
            $this->userInfo .= ':' . $pass;
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;
        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): static
    {
        $clone         = clone $this;
        $clone->scheme = strtolower($scheme);

        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $clone           = clone $this;
        $clone->userInfo = $user;
        if ($password !== null && $password !== '') {
            $clone->userInfo .= ':' . $password;
        }

        return $clone;
    }

    public function withHost(string $host): static
    {
        $clone       = clone $this;
        $clone->host = strtolower($host);

        return $clone;
    }

    public function withPort(?int $port): static
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('Invalid port number.');
        }

        $clone       = clone $this;
        $clone->port = $port;

        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone       = clone $this;
        $clone->path = $path;

        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone        = clone $this;
        $clone->query = ltrim($query, '?');

        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone           = clone $this;
        $clone->fragment = ltrim($fragment, '#');

        return $clone;
    }

    public function __toString(): string
    {
        $scheme    = $this->scheme !== '' ? $this->scheme . ':' : '';
        $authority = $this->getAuthority();
        $path      = $this->path;

        if ($authority !== '') {
            $path = $path === '' ? '/' : $path;
        }

        $query    = $this->query !== '' ? '?' . $this->query : '';
        $fragment = $this->fragment !== '' ? '#' . $this->fragment : '';

        return $scheme . ($authority !== '' ? '//' . $authority : '') . $path . $query . $fragment;
    }
}
