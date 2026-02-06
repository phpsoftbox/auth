<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function preg_match;
use function preg_quote;
use function str_contains;
use function str_replace;

final class PermissionPolicyRegistry
{
    /**
     * @var array<string, callable>
     */
    private array $policies = [];

    public function define(string $permission, callable $rule): self
    {
        $this->policies[$permission] = $rule;

        return $this;
    }

    public function definePattern(string $pattern, callable $rule): self
    {
        $this->policies[$pattern] = $rule;

        return $this;
    }

    public function allows(mixed $user, string $permission, mixed $subject = null): bool
    {
        $rules = $this->resolvePolicies($permission);
        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($rule($user, $subject, $permission) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<callable>
     */
    private function resolvePolicies(string $permission): array
    {
        $matched = [];

        foreach ($this->policies as $key => $rule) {
            if ($key === $permission) {
                $matched[] = $rule;
                continue;
            }

            if (str_contains($key, '*') && $this->matchesPattern($key, $permission)) {
                $matched[] = $rule;
            }
        }

        return $matched;
    }

    private function matchesPattern(string $pattern, string $permission): bool
    {
        $pattern = preg_quote($pattern, '~');
        $pattern = str_replace('\\*', '.*', $pattern);

        return preg_match('~^' . $pattern . '$~', $permission) === 1;
    }
}
