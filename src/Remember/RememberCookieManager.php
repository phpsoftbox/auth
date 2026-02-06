<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use DateTimeInterface;
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Cookie\SetCookie;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RememberCookieManager
{
    public function __construct(
        private CookieQueue $cookies,
        private RememberCookieConfig $config = new RememberCookieConfig(),
    ) {
    }

    public function queue(string $token, ?DateTimeInterface $expiresAt = null, ?ServerRequestInterface $request = null): void
    {
        $this->cookies->queue($this->create($token, $expiresAt, $request));
    }

    public function queueForget(?ServerRequestInterface $request = null): void
    {
        $this->cookies->queue($this->forget($request));
    }

    public function create(
        string $token,
        ?DateTimeInterface $expiresAt = null,
        ?ServerRequestInterface $request = null,
    ): SetCookie {
        $cookie = SetCookie::create($this->config->name, $token)
            ->withPath($this->config->path)
            ->withDomain($this->config->domain)
            ->withSecure($this->config->secure->resolve($request))
            ->withHttpOnly($this->config->httpOnly)
            ->withSameSite($this->config->sameSite);

        if ($this->config->maxAge !== null) {
            $cookie = $cookie->withMaxAge($this->config->maxAge);
        }

        if ($expiresAt !== null) {
            $cookie = $cookie->withExpires($expiresAt->getTimestamp());
        }

        return $cookie;
    }

    public function forget(?ServerRequestInterface $request = null): SetCookie
    {
        return SetCookie::create($this->config->name, '')
            ->withExpires(1)
            ->withMaxAge(0)
            ->withPath($this->config->path)
            ->withDomain($this->config->domain)
            ->withSecure($this->config->secure->resolve($request))
            ->withHttpOnly($this->config->httpOnly)
            ->withSameSite($this->config->sameSite);
    }
}
