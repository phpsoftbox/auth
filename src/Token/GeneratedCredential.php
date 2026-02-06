<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

/** @internal */
final readonly class GeneratedCredential
{
    public function __construct(
        public string $token,
        public string $selector,
        public string $secretHash,
    ) {
    }
}
