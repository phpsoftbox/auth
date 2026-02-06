<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\Store\TransactionalStoreInterface;

final class TransactionalInMemoryPermissionStore extends InMemoryPermissionStore implements TransactionalStoreInterface
{
    public int $transactions = 0;

    public function transaction(callable $callback): mixed
    {
        $this->transactions++;

        return $callback();
    }
}
