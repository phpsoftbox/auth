<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie;

use InvalidArgumentException;

use function gmdate;
use function rawurlencode;
use function sprintf;

final class SetCookie
{
    private ?int $expires = null;
    private ?int $maxAge = null;
    private ?string $path = '/';
    private ?string $domain = null;
    private bool $secure = true;
    private bool $httpOnly = true;
    private ?SameSite $sameSite = SameSite::Lax;

    public function __construct(
        private string $name,
        private string $value = '',
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Cookie name must not be empty.');
        }
    }

    public static function create(string $name, string $value = ''): self
    {
        return new self($name, $value);
    }

    public function withValue(string $value): self
    {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }

    public function withExpires(?int $timestamp): self
    {
        $clone = clone $this;
        $clone->expires = $timestamp;

        return $clone;
    }

    public function withMaxAge(?int $seconds): self
    {
        $clone = clone $this;
        $clone->maxAge = $seconds;

        return $clone;
    }

    public function withPath(?string $path): self
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    public function withDomain(?string $domain): self
    {
        $clone = clone $this;
        $clone->domain = $domain;

        return $clone;
    }

    public function withSecure(bool $secure = true): self
    {
        $clone = clone $this;
        $clone->secure = $secure;

        return $clone;
    }

    public function withHttpOnly(bool $httpOnly = true): self
    {
        $clone = clone $this;
        $clone->httpOnly = $httpOnly;

        return $clone;
    }

    public function withSameSite(?SameSite $sameSite): self
    {
        $clone = clone $this;
        $clone->sameSite = $sameSite;

        return $clone;
    }

    public function toHeader(): string
    {
        if ($this->sameSite === SameSite::None && !$this->secure) {
            throw new InvalidArgumentException('SameSite=None требует Secure=true.');
        }

        $cookie = sprintf('%s=%s', $this->name, rawurlencode($this->value));

        if ($this->expires !== null) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $this->expires);
        }

        if ($this->maxAge !== null) {
            $cookie .= '; Max-Age=' . $this->maxAge;
        }

        if ($this->path !== null && $this->path !== '') {
            $cookie .= '; Path=' . $this->path;
        }

        if ($this->domain !== null && $this->domain !== '') {
            $cookie .= '; Domain=' . $this->domain;
        }

        if ($this->secure) {
            $cookie .= '; Secure';
        }

        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if ($this->sameSite !== null) {
            $cookie .= '; SameSite=' . $this->sameSite->value;
        }

        return $cookie;
    }
}
