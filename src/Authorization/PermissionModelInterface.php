<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

interface PermissionModelInterface
{
    /**
     * Базовый ресурс (префикс).
     */
    public static function resource(): string;

    /**
     * Скоуп (например base/own).
     */
    public static function scope(): string;

    /**
     * @return list<BackedEnum|string>
     */
    public static function actions(): array;

    /**
     * @return array<string, string>
     */
    public static function labels(): array;
}
