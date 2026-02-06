<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

enum AreaAccessDeniedMode: string
{
    case Redirect  = 'redirect';
    case Forbidden = 'forbidden';
    case NotFound  = 'not_found';
}
