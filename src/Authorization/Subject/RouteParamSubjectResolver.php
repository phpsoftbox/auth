<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;

final readonly class RouteParamSubjectResolver implements SubjectResolverInterface
{
    public function __construct(
        private string $param,
        private ?RouteParameterProviderInterface $routes = null,
    ) {
    }

    public function resolve(ServerRequestInterface $request): RouteParamSubject
    {
        if ($this->routes === null) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Route parameter provider is not configured.',
                context: ['configuration_error' => true],
            ));
        }

        $params = $this->routes->all($request);
        if (!array_key_exists($this->param, $params)) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Route parameter is missing: ' . $this->param,
                context: ['route_param' => $this->param],
            ));
        }

        return new RouteParamSubject([
            $this->param => $params[$this->param],
        ], $this->param);
    }
}
