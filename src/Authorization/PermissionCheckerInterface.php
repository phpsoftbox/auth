<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

interface PermissionCheckerInterface
{
    /**
     * Проверяет, может ли пользователь выполнить действие.
     */
    public function can(mixed $user, string $permission, mixed $subject = null): bool;
}
