<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Authorization\Attribute\RequiresPermission;

#[RequiresPermission('users.base.read')]
final class PermissionController
{
    #[RequiresPermission('users.base.update', 'user')]
    public function update(): void
    {
    }
}
