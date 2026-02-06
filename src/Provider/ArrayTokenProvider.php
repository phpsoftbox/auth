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
     * @param array<string, mixed>|list<array<string, mixed>|object> $tokens
     */
    public function __construct(
        private readonly array $tokens,
        private readonly ?UserProviderInterface $users = null,
        private readonly string $tokenField = 'token',
        private readonly string $subjectField = 'user_id',
    ) {
    }

    public function retrieveUserByToken(string $token): mixed
    {
        if (array_key_exists($token, $this->tokens)) {
            return $this->resolveSubject($this->tokens[$token]);
        }

        foreach ($this->tokens as $row) {
            $tokenValue = $this->extractFieldValue($row, $this->tokenField);
            if ($tokenValue === $token) {
                return $this->resolveSubject($this->extractFieldValue($row, $this->subjectField));
            }
        }

        return null;
    }

    private function resolveSubject(mixed $subject): mixed
    {
        if (!is_int($subject) && !is_string($subject)) {
            return $subject;
        }

        if ($this->users === null) {
            return null;
        }

        return $this->users->retrieveById($subject);
    }

    private function extractFieldValue(mixed $row, string $field): mixed
    {
        if (is_array($row)) {
            return $row[$field] ?? null;
        }

        if (is_object($row) && property_exists($row, $field)) {
            return $row->{$field};
        }

        return null;
    }
}
