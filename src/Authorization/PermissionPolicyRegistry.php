<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;
use InvalidArgumentException;
use PhpSoftBox\Auth\Authorization\Policy\UserAccessPolicyInterface;
use Psr\Container\ContainerInterface;
use Throwable;

use function class_exists;
use function get_debug_type;
use function is_callable;
use function is_string;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

final class PermissionPolicyRegistry
{
    /**
     * @var array<string, callable|class-string>
     */
    private array $policies = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    public function define(string|BackedEnum $permission, callable|string $rule): self
    {
        $permission                  = PermissionName::normalize($permission);
        $this->policies[$permission] = $rule;

        return $this;
    }

    public function definePattern(string $pattern, callable|string $rule): self
    {
        $this->policies[$pattern] = $rule;

        return $this;
    }

    public function allows(mixed $user, string|BackedEnum $permission, mixed $subject = null): bool
    {
        return $this->decide($user, $permission, $subject)->isAllowed();
    }

    public function decide(mixed $user, string|BackedEnum $permission, mixed $subject = null): AccessDecision
    {
        $permission = PermissionName::normalize($permission);

        $rules = $this->resolvePolicies($permission);
        if ($rules === []) {
            return AccessDecision::allow();
        }

        foreach ($rules as $rule) {
            $decision = $this->resolveDecision($this->callRule($rule, $user, $subject, $permission));
            if (!$decision->isAllowed()) {
                return $decision;
            }
        }

        return AccessDecision::allow();
    }

    /**
     * @return list<callable|class-string>
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

    private function callRule(callable|string $rule, mixed $user, mixed $subject, string $permission): mixed
    {
        if (is_string($rule)) {
            $rule = $this->resolveRuleClass($rule);
        }

        if ($rule instanceof UserAccessPolicyInterface) {
            return $rule->decide($user, $permission, $subject);
        }

        return $rule($user, $subject, $permission);
    }

    private function resolveRuleClass(string $class): callable|UserAccessPolicyInterface
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Permission policy class is not found: ' . $class);
        }

        try {
            $rule = $this->container?->has($class) === true
                ? $this->container->get($class)
                : new $class();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Permission policy cannot be created: ' . $class, previous: $exception);
        }

        if ($rule instanceof UserAccessPolicyInterface) {
            return $rule;
        }

        if (!is_callable($rule)) {
            throw new InvalidArgumentException(sprintf(
                'Permission policy must be callable or implement %s, got %s.',
                UserAccessPolicyInterface::class,
                get_debug_type($rule),
            ));
        }

        return $rule;
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
