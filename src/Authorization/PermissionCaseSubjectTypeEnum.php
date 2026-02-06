<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

enum PermissionCaseSubjectTypeEnum: string
{
    case RouteParam       = 'route_param';
    case Ownership        = 'ownership';
    case RequestAttribute = 'request_attribute';
    case Custom           = 'custom';
}
