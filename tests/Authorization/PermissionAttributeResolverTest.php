<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Attribute\RequiresPermission;
use PhpSoftBox\Auth\Authorization\PermissionAttributeResolver;
use PhpSoftBox\Auth\Authorization\PermissionRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionAttributeResolver::class)]
#[CoversClass(RequiresPermission::class)]
#[CoversClass(PermissionRequirement::class)]
final class PermissionAttributeResolverTest extends TestCase
{
    /**
     * Проверяет, что атрибут на методе имеет приоритет над атрибутом класса.
     */
    #[Test]
    public function resolvesMethodAttributeBeforeClass(): void
    {
        $resolver = new PermissionAttributeResolver();

        $requirement = $resolver->resolve([AttributeController::class, 'update']);

        self::assertInstanceOf(PermissionRequirement::class, $requirement);
        self::assertSame('users.base.update', $requirement->permission);
        self::assertSame('user', $requirement->subjectAttribute);
    }

    /**
     * Проверяет, что атрибут класса используется, если на методе его нет.
     */
    #[Test]
    public function resolvesClassAttributeWhenMethodHasNone(): void
    {
        $resolver = new PermissionAttributeResolver();

        $requirement = $resolver->resolve([AttributeController::class, 'index']);

        self::assertInstanceOf(PermissionRequirement::class, $requirement);
        self::assertSame('users.base.read', $requirement->permission);
        self::assertNull($requirement->subjectAttribute);
    }
}

#[RequiresPermission('users.base.read')]
final class AttributeController
{
    public function index(): void
    {
    }

    #[RequiresPermission('users.base.update', 'user')]
    public function update(): void
    {
    }
}
