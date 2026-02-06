<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

enum RememberMismatchPolicy: string
{
    case RevokeToken = 'revoke_token';
    case Logout      = 'logout';
}
