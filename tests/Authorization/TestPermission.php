<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PermissionModel;

final class TestPermission extends PermissionModel
{
    public static function resource(): string
    {
        return 'test';
    }
}
