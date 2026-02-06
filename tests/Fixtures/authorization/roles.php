<?php

declare(strict_types=1);

use PhpSoftBox\Auth\Authorization\RoleDefinition;

return [
    'roles' => [
        RoleDefinition::named('alpha', 'Alpha')
            ->allow('alpha.view'),
    ],
];
