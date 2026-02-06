<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\PermissionModelInterface;

final class PostPermission implements PermissionModelInterface
{
    public static function resource(): string
    {
        return 'posts';
    }

    public static function scope(): string
    {
        return 'base';
    }

    public static function actions(): array
    {
        return [
            PermissionActionEnum::READ,
            PermissionActionEnum::UPDATE,
            PermissionActionEnum::DELETE,
        ];
    }

    public static function labels(): array
    {
        return [];
    }
}
