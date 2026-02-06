<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Attribute\RequiresPermission;

#[RequiresPermission('users.base.read')]
final class AttributeController
{
    public function index(): void
    {
    }

    #[RequiresPermission('users.base.update', 'user')]
    public function update(): void
    {
    }
}
