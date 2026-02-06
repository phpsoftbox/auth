<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Fixtures;

use PhpSoftBox\Orm\Metadata\Attributes\Column;
use PhpSoftBox\Orm\Metadata\Attributes\Entity;

#[Entity(table: 'users')]
final class TestIdentity
{
    #[Column(type: 'int')]
    public int $id;

    #[Column(name: 'marker', type: 'test_marker', nullable: true)]
    public ?string $marker = null;
}
