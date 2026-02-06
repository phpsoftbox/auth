<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function array_merge;

final class CompositeRoleDefinitionProvider implements RoleDefinitionProviderInterface
{
    /**
     * @param list<RoleDefinitionProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {
    }

    public function load(): RoleDefinitionSet
    {
        $roles       = [];
        $models      = [];
        $permissions = [];

        foreach ($this->providers as $provider) {
            $set         = $provider->load();
            $roles       = array_merge($roles, $set->roles);
            $models      = array_merge($models, $set->permissionModels);
            $permissions = array_merge($permissions, $set->permissions);
        }

        $provider = new ArrayRoleDefinitionProvider($roles, $models, $permissions);

        return $provider->load();
    }
}
