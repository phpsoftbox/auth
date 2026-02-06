<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Policy;

use PhpSoftBox\Auth\Contracts\OwnableInterface;
use PhpSoftBox\Auth\Contracts\UserIdentityInterface;

use function is_int;
use function is_string;

final class OwnershipPolicy
{
    public static function by(callable $userIdResolver, callable $ownerIdResolver): callable
    {
        return static function (mixed $user, mixed $subject) use ($userIdResolver, $ownerIdResolver): bool {
            $userId  = $userIdResolver($user);
            $ownerId = $ownerIdResolver($subject);

            if (!is_int($userId) && !is_string($userId)) {
                return false;
            }

            return (string) $userId === (string) $ownerId;
        };
    }

    public static function byInterfaces(): callable
    {
        return static function (mixed $user, mixed $subject): bool {
            if (!$user instanceof UserIdentityInterface || !$subject instanceof OwnableInterface) {
                return false;
            }

            $userId  = $user->getId();
            $ownerId = $subject->getOwnerId();

            if (!is_int($userId) && !is_string($userId)) {
                return false;
            }

            return (string) $userId === (string) $ownerId;
        };
    }
}
