<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;

final readonly class OwnershipSubjectResolver implements SubjectResolverInterface
{
    public function __construct(
        private string $routeParam,
        private OwnershipRegistry $ownership,
        private ?RouteParameterProviderInterface $routes = null,
    ) {
    }

    public function resolve(ServerRequestInterface $request): OwnershipSubject
    {
        if ($this->routes === null) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Route parameter provider is not configured.',
                context: ['configuration_error' => true],
            ));
        }

        $binding = $this->ownership->find($this->routeParam);
        if ($binding === null) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Ownership binding is not configured: ' . $this->routeParam,
                context: ['configuration_error' => true, 'route_param' => $this->routeParam],
            ));
        }

        $params = $this->routes->all($request);
        if (!array_key_exists($this->routeParam, $params)) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Route parameter is missing: ' . $this->routeParam,
                context: ['route_param' => $this->routeParam],
            ));
        }

        $subject = $binding->owner->resolve($params[$this->routeParam], $request, $binding);
        if ($subject === null) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Ownership subject is not found.',
                context: ['http_status' => 404, 'route_param' => $this->routeParam],
            ));
        }

        return $subject;
    }
}
