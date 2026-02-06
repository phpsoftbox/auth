<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

enum PermissionRequirementMode: string
{
    case Single = 'single';
    case Any    = 'any';
    case All    = 'all';
}
