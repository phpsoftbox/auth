<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function property_exists;

class ArrayTokenProvider implements TokenProviderInterface
{
    /**
     * @param array<string, int|string>|list<array<string, mixed>|object> $tokens
     */
    public function __construct(
        private readonly array $tokens,
        private readonly string $tokenField = 'token',
        private readonly string $userIdField = 'user_id',
    ) {
    }

    public function retrieveUserIdByToken(string $token): int|string|null
    {
        if (array_key_exists($token, $this->tokens)) {
            $value = $this->tokens[$token];

            return is_int($value) || is_string($value) ? $value : null;
        }

        foreach ($this->tokens as $row) {
            $tokenValue = null;
            if (is_array($row)) {
                $tokenValue = $row[$this->tokenField] ?? null;
            } elseif (is_object($row) && property_exists($row, $this->tokenField)) {
                $tokenValue = $row->{$this->tokenField};
            }

            if ($tokenValue === $token) {
                $userId = null;
                if (is_array($row)) {
                    $userId = $row[$this->userIdField] ?? null;
                } elseif (is_object($row) && property_exists($row, $this->userIdField)) {
                    $userId = $row->{$this->userIdField};
                }

                return is_int($userId) || is_string($userId) ? $userId : null;
            }
        }

        return null;
    }
}
