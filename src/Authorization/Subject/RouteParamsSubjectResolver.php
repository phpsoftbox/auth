<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;

final readonly class RouteParamsSubjectResolver implements SubjectResolverInterface
{
    /**
     * @param list<string> $params
     */
    public function __construct(
        private array $params,
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

        $routeParams = $this->routes->all($request);
        $subject     = [];
        foreach ($this->params as $param) {
            if (!array_key_exists($param, $routeParams)) {
                throw new SubjectResolutionException(AccessDecision::deny(
                    reason: 'Route parameter is missing: ' . $param,
                    context: ['route_param' => $param],
                ));
            }

            $subject[$param] = $routeParams[$param];
        }

        return new RouteParamSubject($subject, $this->params[0] ?? null);
    }
}
