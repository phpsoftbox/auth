<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

/**
 * Базовые действия для моделей.
 */
enum PermissionActionEnum: string
{
    case CREATE  = 'create';
    case READ    = 'read';
    case UPDATE  = 'update';
    case DELETE  = 'delete';
    case RESTORE = 'restore';
}
