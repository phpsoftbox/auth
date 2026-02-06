<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

interface PermissionCheckerInterface
{
    /**
     * Проверяет, может ли пользователь выполнить действие.
     */
    public function can(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool;
}
