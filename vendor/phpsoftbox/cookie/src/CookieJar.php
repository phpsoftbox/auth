<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie;

final class CookieJar
{
    /**
     * @return array<string, Cookie>
     */
    public static function fromHeader(string $header): array
    {
        return Cookie::parseHeader($header);
    }

    /**
     * @param list<SetCookie> $cookies
     * @return list<string>
     */
    public static function toHeaders(array $cookies): array
    {
        $headers = [];
        foreach ($cookies as $cookie) {
            $headers[] = $cookie->toHeader();
        }

        return $headers;
    }
}
