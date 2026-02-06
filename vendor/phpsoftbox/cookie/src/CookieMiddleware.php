<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie;

use PhpSoftBox\Cookie\Cookie;
use PhpSoftBox\Cookie\CookieJar;
use PhpSoftBox\Cookie\CookieQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CookieMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CookieQueue $queue = new CookieQueue(),
        private readonly string $attribute = 'cookie_queue',
        private readonly bool $parseHeader = true,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->parseHeader && $request->getCookieParams() === []) {
            $header = $request->getHeaderLine('Cookie');
            if ($header !== '') {
                $parsed = Cookie::parseHeader($header);
                $params = [];
                foreach ($parsed as $cookie) {
                    $params[$cookie->name] = $cookie->value;
                }
                $request = $request->withCookieParams($params);
            }
        }

        $request = $request->withAttribute($this->attribute, $this->queue);

        $response = $handler->handle($request);

        foreach (CookieJar::toHeaders($this->queue->flush()) as $header) {
            $response = $response->withAddedHeader('Set-Cookie', $header);
        }

        return $response;
    }
}
