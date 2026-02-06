<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\PermissionGrant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionGrant::class)]
final class PermissionGrantTest extends TestCase
{
    /**
     * Проверяет сохранение metadata в PermissionGrant.
     */
    #[Test]
    public function keepsMetadataValues(): void
    {
        $grant = new PermissionGrant(
            resource: 'dispatcher.users',
            actions: [PermissionActionEnum::UPDATE],
            scope: 'base',
            meta: ['max_manage_rank' => 30, 'allow_self' => false],
        );

        self::assertSame('dispatcher.users', $grant->resource);
        self::assertSame('base', $grant->scope);
        self::assertSame(30, $grant->meta('max_manage_rank'));
        self::assertFalse((bool) $grant->meta('allow_self'));
        self::assertSame('fallback', $grant->meta('unknown', 'fallback'));
    }

    /**
     * Проверяет, что meta по умолчанию пустой.
     */
    #[Test]
    public function hasEmptyMetadataByDefault(): void
    {
        $grant = new PermissionGrant('users.base.read');

        self::assertSame([], $grant->meta);
        self::assertNull($grant->meta('missing'));
    }
}
