<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\ArrayRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\DefinitionPermissionChecker;
use PhpSoftBox\Auth\Authorization\PermissionActionEnum;
use PhpSoftBox\Auth\Authorization\PermissionModelInterface;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PhpSoftBox\Auth\Authorization\RolePermissionMap;
use PhpSoftBox\Auth\Authorization\RolePermissionSet;
use PhpSoftBox\Auth\Authorization\UserRoleResolver;
use PhpSoftBox\Auth\Contracts\UserRolesInterface;
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
     * Проверяет, что adminAccess даёт право на admin.access.
     */
    #[Test]
    public function allowsAdminPermissionViaAdminAccess(): void
    {
        $provider = new ArrayRoleDefinitionProvider(
            roles: [
                RoleDefinition::admin(),
            ],
            permissions: ['admin.access'],
        );

        $checker = new DefinitionPermissionChecker($provider, new UserRoleResolver(), adminPermission: 'admin.access');

        $user = new RoleUser(['admin']);

        self::assertTrue($checker->can($user, 'admin.access'));
    }
}

final class RoleUser implements UserRolesInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private array $roles,
    ) {
    }

    public function getRoleNames(): array
    {
        return $this->roles;
    }
}

final class PostPermission implements PermissionModelInterface
{
    public static function resource(): string
    {
        return 'posts';
    }

    public static function scope(): string
    {
        return 'base';
    }

    public static function actions(): array
    {
        return [
            PermissionActionEnum::READ,
            PermissionActionEnum::UPDATE,
            PermissionActionEnum::DELETE,
        ];
    }

    public static function labels(): array
    {
        return [];
    }
}
