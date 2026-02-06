<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function preg_replace;
use function str_contains;
use function str_replace;
use function strlen;
use function strtolower;
use function trim;

final class PermissionResourceNamer
{
    private function __construct()
    {
    }

    public static function normalize(string $resource): string
    {
        $resource = trim($resource);
        if ($resource === '') {
            return $resource;
        }

        if (str_contains($resource, '\\')) {
            $resource = (string) preg_replace('~^.*\\\\~', '', $resource);
        }

        $suffix = 'Permission';
        if (strlen($resource) > strlen($suffix)) {
            $resource = (string) preg_replace('~Permission$~', '', $resource);
        }
        $resource = (string) preg_replace('~(?<!^)[A-Z]~', '-$0', $resource);
        $resource = strtolower($resource);
        $resource = str_replace(['_', ' '], '-', $resource);
        $resource = trim($resource, '-');

        return $resource;
    }
}
