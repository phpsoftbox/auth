<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

/** @internal */
final readonly class ParsedCredential
{
    public function __construct(
        public string $selector,
        public string $secret,
    ) {
    }
}
