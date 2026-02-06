<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Manager;

use InvalidArgumentException;
use PhpSoftBox\Auth\Authorization\PermissionCheckerInterface;
use PhpSoftBox\Auth\Guard\GuardInterface;
use Psr\Container\ContainerInterface;
use Throwable;

use function class_exists;
use function get_debug_type;
use function is_callable;
use function is_string;

final class AuthManager
{
    /** @var array<string, GuardInterface|callable|class-string> */
    private array $guards;

    /** @var array<string, GuardInterface> */
    private array $resolved = [];

    public function __construct(
        array $guards = [],
        private string $defaultGuard = 'web',
        private readonly ?ContainerInterface $container = null,
        private readonly ?PermissionCheckerInterface $permissions = null,
    ) {
        $this->guards = $guards;
    }

    public function guard(?string $name = null): GuardInterface
    {
        $name = $name ?: $this->defaultGuard;
        if (!isset($this->guards[$name])) {
            throw new InvalidArgumentException("Guard not registered: {$name}");
        }

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $guard                 = $this->resolve($this->guards[$name]);
        $this->resolved[$name] = $guard;

        return $guard;
    }

    public function defaultGuard(): string
    {
        return $this->defaultGuard;
    }

    public function shouldUse(string $name): void
    {
        $this->defaultGuard = $name;
    }

    public function extend(string $name, GuardInterface|callable|string $guard): void
    {
        $this->guards[$name] = $guard;
        unset($this->resolved[$name]);
    }

    public function can(mixed $user, string $permission, mixed $subject = null): bool
    {
        if ($this->permissions === null) {
            return false;
        }

        return $this->permissions->can($user, $permission, $subject);
    }

    private function resolve(GuardInterface|callable|string $guard): GuardInterface
    {
        if ($guard instanceof GuardInterface) {
            return $guard;
        }

        if (is_callable($guard)) {
            $resolved = $guard();
            if (!$resolved instanceof GuardInterface) {
                $type = get_debug_type($resolved);

                throw new InvalidArgumentException("Guard factory must return GuardInterface, got {$type}.");
            }

            return $resolved;
        }

        if (is_string($guard) && class_exists($guard)) {
            if ($this->container !== null) {
                try {
                    $instance = $this->container->get($guard);
                } catch (Throwable) {
                    $instance = new $guard();
                }
            } else {
                $instance = new $guard();
            }
            if (!$instance instanceof GuardInterface) {
                $type = get_debug_type($instance);

                throw new InvalidArgumentException("Guard must implement GuardInterface, got {$type}.");
            }

            return $instance;
        }

        $type = get_debug_type($guard);

        throw new InvalidArgumentException("Unsupported guard definition: {$type}");
    }
}
