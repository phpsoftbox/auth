<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

final readonly class AccessDecision
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function allow(array $context = []): self
    {
        return new self(true, null, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function deny(?string $reason = null, array $context = []): self
    {
        return new self(false, $reason, $context);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }
}
