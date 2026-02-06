<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use InvalidArgumentException;
use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionCaseSubjectTypeEnum;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

use function class_exists;
use function get_debug_type;
use function is_string;
use function sprintf;

final readonly class PermissionCaseSubjectResolver
{
    public function __construct(
        private ?RouteParameterProviderInterface $routes = null,
        private ?OwnershipRegistry $ownership = null,
        private ?ContainerInterface $container = null,
        private bool $debug = false,
    ) {
    }

    public function resolve(ServerRequestInterface $request, PermissionCase $case): mixed
    {
        try {
            return $this->resolver($case)->resolve($request);
        } catch (SubjectResolutionException $exception) {
            if ($this->debug && ($exception->decision->context['configuration_error'] ?? false) === true) {
                throw new RuntimeException($exception->getMessage(), previous: $exception);
            }

            throw $exception;
        }
    }

    private function resolver(PermissionCase $case): SubjectResolverInterface
    {
        if ($case->subject instanceof SubjectResolverInterface) {
            return $case->subject;
        }

        if ($case->subjectType === null) {
            return new class () implements SubjectResolverInterface {
                public function resolve(ServerRequestInterface $request): mixed
                {
                    return null;
                }
            };
        }

        return match ($case->subjectType) {
            PermissionCaseSubjectTypeEnum::RouteParam       => $this->routeParamResolver($case),
            PermissionCaseSubjectTypeEnum::Ownership        => $this->ownershipResolver($case),
            PermissionCaseSubjectTypeEnum::RequestAttribute => $this->requestAttributeResolver($case),
            PermissionCaseSubjectTypeEnum::Custom           => $this->customResolver($case),
        };
    }

    private function routeParamResolver(PermissionCase $case): SubjectResolverInterface
    {
        return new RouteParamSubjectResolver($this->stringSubject($case), $this->routes);
    }

    private function ownershipResolver(PermissionCase $case): SubjectResolverInterface
    {
        if ($this->ownership === null) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Ownership registry is not configured.',
                context: ['configuration_error' => true],
            ));
        }

        return new OwnershipSubjectResolver($this->stringSubject($case), $this->ownership, $this->routes);
    }

    private function requestAttributeResolver(PermissionCase $case): SubjectResolverInterface
    {
        return new RequestAttributeSubjectResolver($this->stringSubject($case));
    }

    private function customResolver(PermissionCase $case): SubjectResolverInterface
    {
        if ($case->subject instanceof SubjectResolverInterface) {
            return $case->subject;
        }

        if (!is_string($case->subject) || $case->subject === '') {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Custom subject resolver is not specified.',
                context: ['configuration_error' => true],
            ));
        }

        if (!class_exists($case->subject)) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Custom subject resolver class is not found: ' . $case->subject,
                context: ['configuration_error' => true, 'resolver' => $case->subject],
            ));
        }

        try {
            $resolver = $this->container?->has($case->subject) === true
                ? $this->container->get($case->subject)
                : new $case->subject();
        } catch (Throwable $exception) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Custom subject resolver cannot be created: ' . $case->subject,
                context: ['configuration_error' => true, 'resolver' => $case->subject],
            ), previous: $exception);
        }

        if (!$resolver instanceof SubjectResolverInterface) {
            throw new InvalidArgumentException(sprintf(
                'Custom subject resolver must implement %s, got %s.',
                SubjectResolverInterface::class,
                get_debug_type($resolver),
            ));
        }

        return $resolver;
    }

    private function stringSubject(PermissionCase $case): string
    {
        if (!is_string($case->subject) || $case->subject === '') {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Permission case subject is not specified.',
                context: ['configuration_error' => true, 'permission' => $case->permission],
            ));
        }

        return $case->subject;
    }
}
