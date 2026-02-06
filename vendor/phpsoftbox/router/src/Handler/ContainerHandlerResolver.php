<?php

declare(strict_types=1);

namespace PhpSoftBox\Router\Handler;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

use function class_exists;
use function count;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function method_exists;

final class ContainerHandlerResolver implements HandlerResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function resolve(callable|array|string $handler): callable
    {
        if (is_string($handler) && class_exists($handler)) {
            $instance = $this->resolveInstance($handler);
            if (method_exists($instance, '__invoke')) {
                return $this->wrapCallable([$instance, '__invoke']);
            }
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (is_object($class) && is_callable([$class, $method])) {
                return $this->wrapCallable([$class, $method]);
            }

            if (is_string($class) && class_exists($class)) {
                $controller = $this->resolveInstance($class);

                if (is_callable([$controller, $method])) {
                    return $this->wrapCallable([$controller, $method]);
                }
            }
        }

        if (is_callable($handler)) {
            return $this->wrapCallable($handler);
        }

        throw new RuntimeException('Invalid handler');
    }

    private function resolveInstance(string $class): object
    {
        try {
            return $this->container->get($class);
        } catch (Throwable) {
            return new $class();
        }
    }

    private function wrapCallable(callable $callable): callable
    {
        return function (ServerRequestInterface $request) use ($callable) {
            if (method_exists($this->container, 'call')) {
                $params = $request->getAttributes();
                unset($params['_route'], $params['_route_params']);
                $params['request'] = $request;

                return $this->container->call($callable, $params);
            }

            return $callable($request);
        };
    }
}
