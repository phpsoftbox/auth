<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\DefaultPermissionNameFormatter;
use PhpSoftBox\Auth\Authorization\PermissionResourceNamer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultPermissionNameFormatter::class)]
#[CoversClass(PermissionResourceNamer::class)]
final class PermissionNameFormatterTest extends TestCase
{
    /**
     * Проверяет генерацию базового имени права.
     */
    #[Test]
    public function formatsPermissionName(): void
    {
        $formatter = new DefaultPermissionNameFormatter();

        self::assertSame('user.base.create', $formatter->format('User', 'create', 'base'));
        self::assertSame('user-profile.base.read', $formatter->format('UserProfile', 'read', 'base'));
    }
}
