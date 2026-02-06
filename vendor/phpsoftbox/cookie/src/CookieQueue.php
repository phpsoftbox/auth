<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie;

use PhpSoftBox\Cookie\SetCookie;

final class CookieQueue
{
    /** @var list<SetCookie> */
    private array $cookies = [];

    public function queue(SetCookie $cookie): void
    {
        $this->cookies[] = $cookie;
    }

    /**
     * @return list<SetCookie>
     */
    public function flush(): array
    {
        $cookies = $this->cookies;
        $this->cookies = [];

        return $cookies;
    }
}
