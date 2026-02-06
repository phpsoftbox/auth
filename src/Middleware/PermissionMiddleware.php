<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use BackedEnum;
use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\PermissionAttributeResolver;
use PhpSoftBox\Auth\Authorization\PermissionCase;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;
use PhpSoftBox\Auth\Authorization\PermissionName;
use PhpSoftBox\Auth\Authorization\PermissionRequirement;
use PhpSoftBox\Auth\Authorization\PermissionRequirementMode;
use PhpSoftBox\Auth\Authorization\Subject\PermissionCaseSubjectResolver;
use PhpSoftBox\Auth\Authorization\Subject\RequestAttributeRouteParameterProvider;
use PhpSoftBox\Auth\Authorization\Subject\SubjectResolutionException;
use PhpSoftBox\Auth\Exception\PermissionDeniedException;
use PhpSoftBox\Auth\Manager\AuthManager;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function is_array;
use function is_string;

final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly string|BackedEnum|null $permission = null,
        private readonly ?string $guardName = null,
        private readonly string $permissionAttribute = '_permission',
        private readonly string $subjectAttribute = '_permission_subject',
        private readonly bool $requireResolvedPermission = true,
        private readonly PermissionAttributeResolver $resolver = new PermissionAttributeResolver(),
        private readonly ?PermissionDeniedHandlerInterface $deniedHandler = null,
        private readonly ?ResponseFactoryInterface $responses = null,
        private readonly ?PermissionCaseSubjectResolver $subjectResolver = null,
        private readonly string $permissionRequirementAttribute = '_permission_requirement',
        private readonly string $permissionCasesAttribute = '_permission_cases',
        private readonly string $permissionModeAttribute = '_permission_mode',
        private readonly string $permissionDeniedModeAttribute = '_permission_denied_mode',
        private readonly ?string $redirectTo = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $permission = $this->permission !== null
            ? PermissionName::normalize($this->permission)
            : (PermissionName::from($request->getAttribute($this->permissionAttribute)) ?? '');
        $subject     = $request->getAttribute($this->subjectAttribute, null);
        $requirement = $this->requestRequirement($request);

        if ($this->permission === null && $requirement !== null) {
            if ($requirement->hasCases()) {
                return $this->processRequirement($request, $handler, $requirement);
            }

            if ($permission === '' && $requirement->permission !== '') {
                $permission = $requirement->permission;
            }

            if ($subject === null && $requirement->subjectAttribute !== null) {
                $subject = $request->getAttribute($requirement->subjectAttribute, null);
            }
        }

        if ($permission === '') {
            $routeHandler = $request->getAttribute('_route_handler');
            if ($routeHandler !== null) {
                $requirement = $this->resolver->resolve($routeHandler);
                if ($requirement !== null) {
                    if ($requirement->hasCases()) {
                        return $this->processRequirement($request, $handler, $requirement);
                    }

                    $permission = $requirement->permission;
                    if ($subject === null && $requirement->subjectAttribute !== null) {
                        $subject = $request->getAttribute($requirement->subjectAttribute, null);
                    }
                }
            }
        }

        if ($permission === '') {
            if ($this->requireResolvedPermission) {
                throw new RuntimeException('Permission is not resolved for request.');
            }

            return $handler->handle($request);
        }

        $guard = $this->auth->guard($this->guardName);
        $user  = $guard->user($request);

        $decision = $user === null
            ? AccessDecision::deny('User is not authenticated.')
            : $this->auth->decide($user, $permission, $subject);

        if (!$decision->isAllowed()) {
            return $this->deny($request, $decision, $permission, $subject, $user);
        }

        return $handler->handle($request);
    }

    private function processRequirement(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        PermissionRequirement $requirement,
    ): ResponseInterface {
        $guard = $this->auth->guard($this->guardName);
        $user  = $guard->user($request);

        if ($user === null) {
            $permission = $requirement->cases[0]->permission ?? '';

            return $this->deny(
                $request,
                AccessDecision::deny('User is not authenticated.'),
                $permission,
                null,
                null,
                $requirement->deniedMode,
            );
        }

        [$decision, $permission, $subject] = $requirement->isAll()
            ? $this->decideAll($request, $requirement, $user)
            : $this->decideAny($request, $requirement, $user);
        if ($decision->isAllowed()) {
            return $handler->handle($request);
        }

        return $this->deny($request, $decision, $permission, $subject, $user, $requirement->deniedMode);
    }

    /**
     * @return array{0: AccessDecision, 1: string, 2: mixed}
     */
    private function decideAny(ServerRequestInterface $request, PermissionRequirement $requirement, mixed $user): array
    {
        $lastDecision   = AccessDecision::deny('Permission denied.');
        $lastPermission = '';
        $lastSubject    = null;

        foreach ($requirement->cases as $case) {
            $lastPermission = $case->permission;

            try {
                $subject = $this->resolveSubject($request, $case);
            } catch (SubjectResolutionException $exception) {
                $lastDecision = $exception->decision;
                $lastSubject  = null;
                continue;
            }

            $decision = $this->auth->decide($user, $case->permission, $subject);
            if ($decision->isAllowed()) {
                return [$decision, $case->permission, $subject];
            }

            $lastDecision = $decision;
            $lastSubject  = $subject;
        }

        return [$lastDecision, $lastPermission, $lastSubject];
    }

    /**
     * @return array{0: AccessDecision, 1: string, 2: mixed}
     */
    private function decideAll(ServerRequestInterface $request, PermissionRequirement $requirement, mixed $user): array
    {
        $lastPermission = '';
        $lastSubject    = null;

        foreach ($requirement->cases as $case) {
            $lastPermission = $case->permission;

            try {
                $subject = $this->resolveSubject($request, $case);
            } catch (SubjectResolutionException $exception) {
                return [$exception->decision, $case->permission, null];
            }

            $decision = $this->auth->decide($user, $case->permission, $subject);
            if (!$decision->isAllowed()) {
                return [$decision, $case->permission, $subject];
            }

            $lastSubject = $subject;
        }

        return [AccessDecision::allow(), $lastPermission, $lastSubject];
    }

    private function resolveSubject(ServerRequestInterface $request, PermissionCase $case): mixed
    {
        return $this->subjectResolver()->resolve($request, $case);
    }

    private function subjectResolver(): PermissionCaseSubjectResolver
    {
        return $this->subjectResolver ?? new PermissionCaseSubjectResolver(
            routes: new RequestAttributeRouteParameterProvider(),
        );
    }

    private function deny(
        ServerRequestInterface $request,
        AccessDecision $decision,
        string $permission,
        mixed $subject = null,
        mixed $user = null,
        PermissionDeniedMode $mode = PermissionDeniedMode::Forbidden,
    ): ResponseInterface {
        if ($this->deniedHandler instanceof PermissionDecisionDeniedHandlerInterface) {
            return $this->deniedHandler->handleDecision(
                $request,
                $this->decisionWithDeniedMode($decision, $mode),
                $permission,
                $subject,
                $user,
            );
        }

        if ($this->deniedHandler !== null) {
            return $this->deniedHandler->handle($request, $permission, $subject, $user);
        }

        $status = $this->statusFromDecision($decision, $mode);
        if ($this->responses !== null && $mode === PermissionDeniedMode::Redirect && $this->redirectTo !== null) {
            return $this->responses
                ->createResponse(303)
                ->withHeader('Location', $this->redirectTo);
        }

        if ($this->responses !== null && ($status === 403 || $status === 404)) {
            return $this->responses->createResponse($status);
        }

        throw new PermissionDeniedException($decision->reason ?? ('Permission denied: ' . $permission));
    }

    private function statusFromDecision(AccessDecision $decision, PermissionDeniedMode $mode): int
    {
        $status = $decision->context['http_status'] ?? null;
        if ($status === 403 || $status === 404) {
            return $status;
        }

        return match ($mode) {
            PermissionDeniedMode::NotFound  => 404,
            PermissionDeniedMode::Forbidden => 403,
            PermissionDeniedMode::Redirect  => 403,
        };
    }

    private function decisionWithDeniedMode(AccessDecision $decision, PermissionDeniedMode $mode): AccessDecision
    {
        if (isset($decision->context['denied_mode'])) {
            return $decision;
        }

        return new AccessDecision(
            allowed: $decision->allowed,
            reason: $decision->reason,
            context: $decision->context + ['denied_mode' => $mode],
        );
    }

    private function requestRequirement(ServerRequestInterface $request): ?PermissionRequirement
    {
        $value = $request->getAttribute($this->permissionRequirementAttribute);
        if ($value instanceof PermissionRequirement) {
            return $value;
        }

        if (is_array($value)) {
            return $this->requirementFromArray($value);
        }

        $cases = $request->getAttribute($this->permissionCasesAttribute);
        if (is_array($cases)) {
            return $this->caseRequirement(
                $cases,
                $request->getAttribute($this->permissionModeAttribute, PermissionRequirementMode::Any),
                $request->getAttribute($this->permissionDeniedModeAttribute, PermissionDeniedMode::Forbidden),
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requirementFromArray(array $data): PermissionRequirement
    {
        if (isset($data['cases']) && is_array($data['cases'])) {
            return $this->caseRequirement(
                $data['cases'],
                $data['mode'] ?? PermissionRequirementMode::Any,
                $data['deniedMode'] ?? $data['denied_mode'] ?? PermissionDeniedMode::Forbidden,
            );
        }

        $permission = PermissionName::from($data['permission'] ?? null) ?? '';

        $subject = $data['subject'] ?? $data['subject_attribute'] ?? null;
        if (!is_string($subject)) {
            $subject = null;
        }

        return PermissionRequirement::single($permission, $subject);
    }

    /**
     * @param array<mixed> $cases
     */
    private function caseRequirement(
        array $cases,
        PermissionRequirementMode|string $mode,
        PermissionDeniedMode|string $deniedMode,
    ): PermissionRequirement {
        $cases      = $this->normalizeCases($cases);
        $mode       = $this->normalizeRequirementMode($mode);
        $deniedMode = $this->normalizeDeniedMode($deniedMode);

        return $mode === PermissionRequirementMode::All
            ? PermissionRequirement::all($cases, $deniedMode)
            : PermissionRequirement::any($cases, $deniedMode);
    }

    /**
     * @param array<mixed> $cases
     * @return list<PermissionCase>
     */
    private function normalizeCases(array $cases): array
    {
        $normalized = [];
        foreach ($cases as $case) {
            if ($case instanceof PermissionCase) {
                $normalized[] = $case;
                continue;
            }

            if (is_string($case) && $case !== '') {
                $normalized[] = PermissionCase::make($case);
                continue;
            }

            if ($case instanceof BackedEnum) {
                $normalized[] = PermissionCase::make($case);
                continue;
            }

            if (is_array($case)) {
                $normalized[] = PermissionCase::fromArray($case);
            }
        }

        return $normalized;
    }

    private function normalizeRequirementMode(PermissionRequirementMode|string $mode): PermissionRequirementMode
    {
        if ($mode instanceof PermissionRequirementMode) {
            return $mode;
        }

        return PermissionRequirementMode::from($mode);
    }

    private function normalizeDeniedMode(PermissionDeniedMode|string $mode): PermissionDeniedMode
    {
        if ($mode instanceof PermissionDeniedMode) {
            return $mode;
        }

        return PermissionDeniedMode::from($mode);
    }
}
