<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\PermissionPolicyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PermissionPolicyRegistry::class)]
#[CoversClass(AccessDecision::class)]
final class PermissionPolicyRegistryTest extends TestCase
{
    /**
     * Проверяет, что для отсутствующих policy доступ разрешен.
     */
    #[Test]
    public function allowsWhenPoliciesAreNotDefined(): void
    {
        $registry = new PermissionPolicyRegistry();

        self::assertTrue($registry->allows(new stdClass(), 'posts.base.read'));
        self::assertTrue($registry->decide(new stdClass(), 'posts.base.read')->isAllowed());
    }

    /**
     * Проверяет deny при boolean false из правила.
     */
    #[Test]
    public function deniesWhenRuleReturnsFalse(): void
    {
        $registry = new PermissionPolicyRegistry()
            ->define('posts.base.delete', static fn (): bool => false);

        $decision = $registry->decide(new stdClass(), 'posts.base.delete');

        self::assertFalse($decision->isAllowed());
        self::assertNull($decision->reason);
    }

    /**
     * Проверяет deny с reason при строковом результате policy.
     */
    #[Test]
    public function mapsStringResultToDecisionReason(): void
    {
        $registry = new PermissionPolicyRegistry()
            ->define('posts.base.update', static fn (): string => 'Недостаточно прав.');

        $decision = $registry->decide(new stdClass(), 'posts.base.update');

        self::assertFalse($decision->isAllowed());
        self::assertSame('Недостаточно прав.', $decision->reason);
        self::assertFalse($registry->allows(new stdClass(), 'posts.base.update'));
    }

    /**
     * Проверяет, что AccessDecision из policy возвращается без потери данных.
     */
    #[Test]
    public function keepsAccessDecisionFromRule(): void
    {
        $registry = new PermissionPolicyRegistry()
            ->define(
                'posts.base.restore',
                static fn (): AccessDecision => AccessDecision::deny(
                    reason: 'Только для владельца.',
                    context: ['owner_id' => 15],
                ),
            );

        $decision = $registry->decide(new stdClass(), 'posts.base.restore');

        self::assertFalse($decision->isAllowed());
        self::assertSame('Только для владельца.', $decision->reason);
        self::assertSame(['owner_id' => 15], $decision->context);
    }

    /**
     * Проверяет wildcard-policy.
     */
    #[Test]
    public function appliesPatternPolicies(): void
    {
        $registry = new PermissionPolicyRegistry()
            ->definePattern('posts.base.*', static fn (): bool => true)
            ->define('posts.base.delete', static fn (): bool => false);

        self::assertTrue($registry->allows(new stdClass(), 'posts.base.read'));
        self::assertFalse($registry->allows(new stdClass(), 'posts.base.delete'));
    }
}
