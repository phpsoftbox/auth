<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PermissionCheckerInterface;
use PhpSoftBox\Auth\Authorization\PermissionGate;
use PhpSoftBox\Auth\Authorization\PermissionPolicyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PermissionGate::class)]
final class PermissionGateTest extends TestCase
{
    /**
     * Проверяет, что при deny от checker policy не вызывается.
     */
    #[Test]
    public function deniesWhenCheckerDenies(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return false;
            }
        };

        $policyCalls = 0;
        $policies    = new PermissionPolicyRegistry()
            ->define('posts.base.read', static function () use (&$policyCalls): bool {
                $policyCalls++;

                return true;
            });

        $gate = new PermissionGate($checker, $policies);

        self::assertFalse($gate->can(new stdClass(), 'posts.base.read'));
        self::assertSame(0, $policyCalls);
    }

    /**
     * Проверяет успешный доступ, когда checker и policy разрешают.
     */
    #[Test]
    public function allowsWhenCheckerAndPolicyAllow(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return true;
            }
        };

        $policies = new PermissionPolicyRegistry()
            ->define('posts.base.read', static fn (): bool => true);

        $gate = new PermissionGate($checker, $policies);

        self::assertTrue($gate->can(new stdClass(), 'posts.base.read'));
    }

    /**
     * Проверяет deny, когда policy запрещает доступ.
     */
    #[Test]
    public function deniesWhenPolicyDenies(): void
    {
        $checker = new class () implements PermissionCheckerInterface {
            public function can(mixed $user, string $permission, mixed $subject = null): bool
            {
                return true;
            }
        };

        $policies = new PermissionPolicyRegistry()
            ->define('posts.base.delete', static fn (): bool => false);

        $gate = new PermissionGate($checker, $policies);

        self::assertFalse($gate->can(new stdClass(), 'posts.base.delete'));
    }
}
