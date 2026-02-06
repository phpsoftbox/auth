<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionCaseSubjectTypeEnum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermissionCase::class)]
final class PermissionCaseTest extends TestCase
{
    /**
     * Проверяет нормализацию BackedEnum в helper API PermissionCase.
     */
    #[Test]
    public function helperAcceptsBackedEnum(): void
    {
        $case = PermissionCase::routeParam(TestPermissionEnum::UsersUpdate, 'user');

        self::assertSame('users.base.update', $case->permission);
        self::assertSame(PermissionCaseSubjectTypeEnum::RouteParam, $case->subjectType);
        self::assertSame('user', $case->subject);
    }

    /**
     * Проверяет нормализацию BackedEnum из array-конфига.
     */
    #[Test]
    public function fromArrayAcceptsBackedEnum(): void
    {
        $case = PermissionCase::fromArray([
            'permission'   => TestPermissionEnum::UsersRead,
            'subject_type' => PermissionCaseSubjectTypeEnum::RequestAttribute->value,
            'subject'      => 'document',
        ]);

        self::assertSame('users.base.read', $case->permission);
        self::assertSame(PermissionCaseSubjectTypeEnum::RequestAttribute, $case->subjectType);
        self::assertSame('document', $case->subject);
    }
}
