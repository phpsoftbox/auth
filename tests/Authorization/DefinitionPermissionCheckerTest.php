<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\DefinitionPermissionChecker;
use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RolePermissionMap;
use PhpSoftBox\Auth\Authorization\RolePermissionSet;
use PhpSoftBox\Auth\Authorization\UserRoleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefinitionPermissionChecker::class)]
#[CoversClass(RolePermissionMap::class)]
#[CoversClass(RolePermissionSet::class)]
#[CoversClass(UserRoleResolver::class)]
final class DefinitionPermissionCheckerTest extends TestCase
{
    /**
     * Проверяет, что can() учитывает разрешения из RoleDefinition.
     */
    #[Test]
    public function allowsGrantedPermissions(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::named('support')
                    ->allow(PostPermission::class, [PermissionActionEnum::READ]),
            ],
            permissionModels: [PostPermission::class],
        );

        $checker = new DefinitionPermissionChecker($provider, new UserRoleResolver());

        $user = new RoleUser(['support']);

        self::assertTrue($checker->can($user, 'posts.base.read'));
        self::assertFalse($checker->can($user, 'posts.base.update'));
    }

    /**
     * Проверяет, что deny переопределяет allowAll.
     */
    #[Test]
    public function deniesExplicitlyDeniedPermissions(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::admin()->allowAll()->deny('posts.base.delete'),
            ],
            permissionModels: [PostPermission::class],
        );

        $checker = new DefinitionPermissionChecker($provider, new UserRoleResolver());

        $user = new RoleUser(['admin']);

        self::assertTrue($checker->can($user, 'posts.base.read'));
        self::assertFalse($checker->can($user, 'posts.base.delete'));
    }

    /**
     * Проверяет, что флаг adminAccess сам по себе не даёт права.
     */
    #[Test]
    public function deniesAdminPermissionViaAdminAccessFlagOnly(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::admin(),
            ],
            permissions: ['admin.access'],
        );

        $checker = new DefinitionPermissionChecker($provider, new UserRoleResolver());

        $user = new RoleUser(['admin']);

        self::assertFalse($checker->can($user, 'admin.access'));
    }

    /**
     * Проверяет, что admin.access работает при явном grant.
     */
    #[Test]
    public function allowsAdminPermissionViaExplicitGrant(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::admin()->allow('admin.access'),
            ],
            permissions: ['admin.access'],
        );

        $checker = new DefinitionPermissionChecker($provider, new UserRoleResolver());

        $user = new RoleUser(['admin']);

        self::assertTrue($checker->can($user, 'admin.access'));
    }
}
