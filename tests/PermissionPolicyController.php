<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Authorization\Attribute\RequiresAllPermissions;
use PhpSoftBox\Auth\Authorization\Attribute\RequiresAnyPermission;
use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionCaseSubjectTypeEnum;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;

final class PermissionPolicyController
{
    #[RequiresAnyPermission([
        new PermissionCase('tenant.companies.base.read'),
        new PermissionCase(
            'tenant.companies.own.read',
            subjectType: PermissionCaseSubjectTypeEnum::RouteParam,
            subject: 'user',
        ),
    ], deniedMode: PermissionDeniedMode::NotFound)]
    public function userCompanies(): void
    {
    }

    #[RequiresAnyPermission([
        new PermissionCase('tenant.companies.base.read'),
        new PermissionCase(
            'tenant.companies.own.read',
            subjectType: PermissionCaseSubjectTypeEnum::Ownership,
            subject: 'company',
        ),
    ], deniedMode: PermissionDeniedMode::NotFound)]
    public function company(): void
    {
    }

    #[RequiresAllPermissions([
        new PermissionCase('tenant.companies.base.read'),
        new PermissionCase('tenant.companies.audit.read'),
    ])]
    public function audit(): void
    {
    }
}
