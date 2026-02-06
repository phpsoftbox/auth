<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_merge;
use function array_unique;
use function array_values;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function sort;
use function str_contains;

use const SORT_STRING;

final class PhpFileRoleDefinitionProvider implements RoleDefinitionProviderInterface
{
    /**
     * @param list<string>|string $paths
     */
    public function __construct(
        private readonly array|string $paths,
    ) {
    }

    public function load(): RoleDefinitionSet
    {
        $roles       = [];
        $models      = [];
        $permissions = [];

        foreach ($this->collectFiles() as $file) {
            if (!is_file($file)) {
                throw new InvalidArgumentException('Role definition file not found: ' . $file);
            }

            $data = require $file;

            if ($data instanceof RoleDefinitionSet) {
                $roles       = array_merge($roles, $data->roles);
                $models      = array_merge($models, $data->permissionModels);
                $permissions = array_merge($permissions, $data->permissions);
                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            $roles       = array_merge($roles, (array) ($data['roles'] ?? []));
            $models      = array_merge($models, (array) ($data['models'] ?? $data['permissions_models'] ?? []));
            $permissions = array_merge($permissions, (array) ($data['permissions'] ?? []));
        }

        $provider = new ArrayRoleDefinitionProvider($roles, $models, $permissions);

        return $provider->load();
    }

    /**
     * @return list<string>
     */
    private function collectFiles(): array
    {
        $paths = is_array($this->paths) ? $this->paths : [$this->paths];

        $files = [];
        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '') {
                continue;
            }

            if (is_file($path)) {
                $files[] = $path;
                continue;
            }

            if (str_contains($path, '*')) {
                foreach (glob($path) ?: [] as $matched) {
                    if (is_file($matched)) {
                        $files[] = $matched;
                    }
                }
                continue;
            }

            if (is_dir($path)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                );

                foreach ($iterator as $fileInfo) {
                    if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                        $files[] = $fileInfo->getPathname();
                    }
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }
}
