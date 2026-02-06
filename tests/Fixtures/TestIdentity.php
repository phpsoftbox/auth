<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Fixtures;

use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;

#[Entity(table: 'users')]
final class TestIdentity implements UserInterface
{
    #[Column(type: 'int')]
    public int $id;

    #[Column(name: 'marker', type: 'string', nullable: true)]
    public ?string $marker = null;

    public function id(): int|string|null
    {
        return $this->id;
    }
}
