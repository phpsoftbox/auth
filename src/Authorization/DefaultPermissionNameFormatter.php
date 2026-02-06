<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function preg_replace;
use function str_replace;
use function strtolower;
use function trim;

final class DefaultPermissionNameFormatter implements PermissionNameFormatterInterface
{
    public function __construct(
        private readonly string $separator = '.',
    ) {
    }

    public function format(string $resource, string $action, string $scope = 'base'): string
    {
        $resource = PermissionResourceNamer::normalize($resource);
        $action   = $this->normalizeSegment($action);
        $scope    = $this->normalizeSegment($scope);

        return $resource . $this->separator . $scope . $this->separator . $action;
    }

    private function normalizeSegment(string $segment): string
    {
        $segment = strtolower(trim($segment));
        $segment = preg_replace('~[^a-z0-9_-]+~', '-', $segment) ?? $segment;
        $segment = str_replace(['__', '--'], '-', $segment);

        return trim($segment, '-');
    }
}
