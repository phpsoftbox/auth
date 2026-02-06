<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use SensitiveParameter;

use function bin2hex;
use function explode;
use function hash;
use function hash_equals;
use function random_bytes;
use function str_contains;
use function trim;

final readonly class CredentialCodec
{
    public function generate(): GeneratedCredential
    {
        $selector = bin2hex(random_bytes(16));
        $secret   = bin2hex(random_bytes(32));

        return new GeneratedCredential(
            token: $selector . '.' . $secret,
            selector: $selector,
            secretHash: $this->hashSecret($secret),
        );
    }

    public function parse(#[SensitiveParameter] string $credential): ?ParsedCredential
    {
        $credential = trim($credential);
        if ($credential === '' || !str_contains($credential, '.')) {
            return null;
        }

        [$selector, $secret] = explode('.', $credential, 2);
        $selector            = trim($selector);
        $secret              = trim($secret);
        if ($selector === '' || $secret === '') {
            return null;
        }

        return new ParsedCredential($selector, $secret);
    }

    public function verify(#[SensitiveParameter] string $secret, string $hash): bool
    {
        return hash_equals($hash, $this->hashSecret($secret));
    }

    private function hashSecret(#[SensitiveParameter] string $secret): string
    {
        return hash('sha256', $secret);
    }
}
