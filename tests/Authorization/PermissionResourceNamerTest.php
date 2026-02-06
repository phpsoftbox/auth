<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PermissionResourceNamer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionResourceNamer::class)]
final class PermissionResourceNamerTest extends TestCase
{
    /**
     * Проверяет нормализацию ресурсов для типовых имён.
     */
    #[Test]
    public function normalizesResourceNames(): void
    {
        self::assertSame('user', PermissionResourceNamer::normalize('UserPermission'));
        self::assertSame('permission', PermissionResourceNamer::normalize('Permission'));
        self::assertSame('permission', PermissionResourceNamer::normalize('PermissionPermission'));
        self::assertSame('user-profile', PermissionResourceNamer::normalize('UserProfilePermission'));
    }
}
