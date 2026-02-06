<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

abstract class PermissionModel implements PermissionModelInterface
{
    public static function resource(): string
    {
        return PermissionResourceNamer::normalize(static::class);
    }

    public static function scope(): string
    {
        return 'base';
    }

    public static function actions(): array
    {
        return PermissionActionEnum::cases();
    }

    public static function labels(): array
    {
        return [];
    }
}
