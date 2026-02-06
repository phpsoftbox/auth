<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Support;

use PhpSoftBox\Auth\Contracts\UserInterface;

final readonly class AuthTestUser implements UserInterface
{
    public function __construct(
        public int|string|null $id,
        public ?string $email = null,
        public ?string $passwordHash = null,
        public ?string $phoneNumber = null,
        public ?string $authToken = null,
    ) {
    }

    public function id(): int|string|null
    {
        return $this->id;
    }
}
