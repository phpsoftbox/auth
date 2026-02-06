<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Exception;

use RuntimeException;

final class UnauthorizedHttpException extends RuntimeException
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        string $message = 'Unauthorized',
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 401;
    }

    /**
     * @return array<string, string|string[]>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
