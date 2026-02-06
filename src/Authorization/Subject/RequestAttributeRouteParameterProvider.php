<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use Psr\Http\Message\ServerRequestInterface;

use function is_array;

final readonly class RequestAttributeRouteParameterProvider implements RouteParameterProviderInterface
{
    public function __construct(
        private string $attribute = '_route_params',
    ) {
    }

    public function get(ServerRequestInterface $request, string $name): mixed
    {
        $params = $this->all($request);

        return $params[$name] ?? null;
    }

    public function all(ServerRequestInterface $request): array
    {
        $params = $request->getAttribute($this->attribute, []);

        return is_array($params) ? $params : [];
    }
}
