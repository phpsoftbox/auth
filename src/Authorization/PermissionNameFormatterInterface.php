<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

interface PermissionNameFormatterInterface
{
    public function format(string $resource, string $action, string $scope = 'base'): string;
}
