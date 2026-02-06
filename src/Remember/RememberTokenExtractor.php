<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use PhpSoftBox\Auth\Token\TokenExtractorInterface;
use Psr\Http\Message\ServerRequestInterface;

use function explode;
use function is_string;
use function rawurldecode;
use function trim;

final readonly class RememberTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private string $cookieName = 'remember_token',
    ) {
    }

    public function extract(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $value   = $cookies[$this->cookieName] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $header = $request->getHeaderLine('Cookie');
        if ($header === '') {
            return null;
        }

        foreach (explode(';', $header) as $cookie) {
            [$name, $token] = explode('=', $cookie, 2) + [1 => ''];
            if (trim($name) !== $this->cookieName) {
                continue;
            }

            $token = trim(rawurldecode($token));
            if ($token !== '') {
                return $token;
            }
        }

        return null;
    }
}
