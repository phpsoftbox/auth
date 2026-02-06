<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use function is_string;
use function preg_match;
use function preg_quote;
use function str_contains;
use function str_replace;
use function trim;

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
        return $this->decide($user, $permission, $subject)->isAllowed();
    }

    public function decide(mixed $user, string $permission, mixed $subject = null): AccessDecision
    {
        $rules = $this->resolvePolicies($permission);
        if ($rules === []) {
            return AccessDecision::allow();
        }

        foreach ($rules as $rule) {
            $decision = $this->resolveDecision($rule($user, $subject, $permission));
            if (!$decision->isAllowed()) {
                return $decision;
            }
        }

        return AccessDecision::allow();
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

    private function resolveDecision(mixed $result): AccessDecision
    {
        if ($result instanceof AccessDecision) {
            return $result;
        }

        if ($result === true) {
            return AccessDecision::allow();
        }

        if (is_string($result)) {
            $reason = trim($result);

            return AccessDecision::deny($reason !== '' ? $reason : null);
        }

        return AccessDecision::deny();
    }
}
