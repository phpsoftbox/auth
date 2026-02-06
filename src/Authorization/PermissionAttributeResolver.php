<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use PhpSoftBox\Auth\Authorization\Attribute\RequiresAllPermissions;
use PhpSoftBox\Auth\Authorization\Attribute\RequiresAnyPermission;
use PhpSoftBox\Auth\Authorization\Attribute\RequiresPermission;
use ReflectionClass;
use ReflectionMethod;

use function class_exists;
use function is_array;
use function is_string;
use function trim;

final class PermissionAttributeResolver
{
    public function resolve(callable|array|string $handler): ?PermissionRequirement
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0]) && is_string($handler[1])) {
            return $this->resolveFromClassMethod($handler[0], $handler[1]);
        }

        if (is_string($handler) && class_exists($handler)) {
            return $this->resolveFromClassMethod($handler, '__invoke');
        }

        return null;
    }

    private function resolveFromClassMethod(string $class, string $method): ?PermissionRequirement
    {
        if (!class_exists($class)) {
            return null;
        }

        $refClass = new ReflectionClass($class);

        $refMethod = null;
        if ($refClass->hasMethod($method)) {
            $refMethod = $refClass->getMethod($method);
        }

        $attribute = $refMethod ? $this->firstAttribute($refMethod) : null;
        if ($attribute !== null) {
            return $attribute;
        }

        return $this->firstAttribute($refClass);
    }

    private function firstAttribute(ReflectionClass|ReflectionMethod $ref): ?PermissionRequirement
    {
        foreach ($ref->getAttributes(RequiresAnyPermission::class) as $attribute) {
            /** @var RequiresAnyPermission $instance */
            $instance = $attribute->newInstance();
            $cases    = $this->validCases($instance->cases);

            if ($cases !== []) {
                return PermissionRequirement::any($cases, $instance->deniedMode);
            }
        }

        foreach ($ref->getAttributes(RequiresAllPermissions::class) as $attribute) {
            /** @var RequiresAllPermissions $instance */
            $instance = $attribute->newInstance();
            $cases    = $this->validCases($instance->cases);

            if ($cases !== []) {
                return PermissionRequirement::all($cases, $instance->deniedMode);
            }
        }

        foreach ($ref->getAttributes(RequiresPermission::class) as $attribute) {
            /** @var RequiresPermission $instance */
            $instance   = $attribute->newInstance();
            $permission = PermissionName::normalize($instance->permission);
            if ($permission === '') {
                continue;
            }

            return PermissionRequirement::single($permission, $instance->subject);
        }

        return null;
    }

    /**
     * @param array<mixed> $cases
     * @return list<PermissionCase>
     */
    private function validCases(array $cases): array
    {
        $valid = [];
        foreach ($cases as $case) {
            if (!$case instanceof PermissionCase || trim($case->permission) === '') {
                continue;
            }

            $valid[] = $case;
        }

        return $valid;
    }
}
