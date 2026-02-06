<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Policy\OwnershipPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OwnershipPolicy::class)]
final class OwnershipPolicyTest extends TestCase
{
    /**
     * Проверяет, что byInterfaces разрешает доступ владельцу.
     */
    #[Test]
    public function allowsOwnerByInterfaces(): void
    {
        $policy = OwnershipPolicy::byInterfaces();

        $user = new TestUser(10);
        $post = new TestPost(10);

        self::assertTrue($policy($user, $post));
        self::assertFalse($policy(new TestUser(5), $post));
    }

    /**
     * Проверяет, что by(callable) использует переданные резолверы.
     */
    #[Test]
    public function allowsOwnerByResolvers(): void
    {
        $policy = OwnershipPolicy::by(
            static fn (TestUser $user) => $user->id(),
            static fn (TestPost $post) => $post->getOwnerId(),
        );

        self::assertTrue($policy(new TestUser(7), new TestPost(7)));
        self::assertFalse($policy(new TestUser(7), new TestPost(8)));
    }
}
