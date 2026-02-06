<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

enum PermissionDeniedMode: string
{
    case Forbidden = 'forbidden';
    case NotFound  = 'not_found';
    case Redirect  = 'redirect';
}
