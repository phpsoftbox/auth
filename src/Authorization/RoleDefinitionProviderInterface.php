<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

interface RoleDefinitionProviderInterface
{
    public function load(): RoleDefinitionSet;
}
