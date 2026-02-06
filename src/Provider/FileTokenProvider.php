<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use InvalidArgumentException;

use function is_array;
use function sprintf;

final class FileTokenProvider extends ArrayTokenProvider
{
    public function __construct(
        string $path,
        string $tokenField = 'token',
        string $userIdField = 'user_id',
    ) {
        $tokens = require $path;
        if (!is_array($tokens)) {
            throw new InvalidArgumentException(sprintf('Token file "%s" must return array.', $path));
        }

        parent::__construct($tokens, $tokenField, $userIdField);
    }
}
