<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store;

interface TransactionalStoreInterface
{
    public function transaction(callable $callback): mixed;
}
