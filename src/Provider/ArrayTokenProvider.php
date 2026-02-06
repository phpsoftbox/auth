<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Auth\Support\UserAccessor;

use function array_key_exists;
use function is_int;
use function is_string;

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
            $tokenValue = UserAccessor::get($row, $this->tokenField);
            if ($tokenValue === $token) {
                $userId = UserAccessor::get($row, $this->userIdField);
                return is_int($userId) || is_string($userId) ? $userId : null;
            }
        }

        return null;
    }
}
