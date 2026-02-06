<?php

declare(strict_types=1);

namespace PhpSoftBox\Cookie;

use InvalidArgumentException;

use function explode;
use function rawurldecode;
use function str_contains;
use function trim;

final readonly class Cookie
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Cookie name must not be empty.');
        }
    }

    /**
     * @return array<string, Cookie>
     */
    public static function parseHeader(string $header): array
    {
        $items = [];
        foreach (explode(';', $header) as $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $part, 2);
            $name  = trim($name);
            $value = rawurldecode(trim($value));

            if ($name === '') {
                continue;
            }

            $items[$name] = new Cookie($name, $value);
        }

        return $items;
    }
}
