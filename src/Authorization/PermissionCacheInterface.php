<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

/**
 * Проверка прав с кешем, который нужно сбрасывать после изменения ролей и прав пользователя.
 */
interface PermissionCacheInterface
{
    /**
     * Сбрасывает кеш прав одного пользователя.
     */
    public function forgetUser(int|string $userId): void;

    /**
     * Сбрасывает весь кеш.
     */
    public function reset(): void;
}
