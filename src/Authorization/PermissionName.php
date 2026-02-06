<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

use function is_string;
use function trim;

final readonly class PermissionName
{
    public static function normalize(string|BackedEnum $permission): string
    {
        $value = $permission instanceof BackedEnum ? $permission->value : $permission;

        return trim((string) $value);
    }

    public static function from(mixed $permission): ?string
    {
        if (!$permission instanceof BackedEnum && !is_string($permission)) {
            return null;
        }

        $normalized = self::normalize($permission);

        return $normalized !== '' ? $normalized : null;
    }
}
