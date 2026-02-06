<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use PhpSoftBox\Auth\Authorization\PermissionDeniedMode;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

final readonly class PermissionHttpDeniedHandler implements PermissionDecisionDeniedHandlerInterface
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private PermissionDeniedMode $defaultMode = PermissionDeniedMode::Forbidden,
        private ?string $redirectTo = null,
    ) {
    }

    public function handleDecision(
        ServerRequestInterface $request,
        AccessDecision $decision,
        string $permission,
        mixed $subject = null,
        mixed $user = null,
    ): ResponseInterface {
        $mode       = $this->modeFromDecision($decision);
        $redirectTo = $this->redirectTo($decision);
        if ($mode === PermissionDeniedMode::Redirect && $redirectTo !== null) {
            return $this->responses
                ->createResponse(303)
                ->withHeader('Location', $redirectTo);
        }

        $status = $this->statusFromDecision($decision, $mode);

        return $this->responses->createResponse($status);
    }

    public function handle(
        ServerRequestInterface $request,
        string $permission,
        mixed $subject = null,
        mixed $user = null,
    ): ResponseInterface {
        return $this->handleDecision(
            $request,
            AccessDecision::deny('Permission denied: ' . $permission),
            $permission,
            $subject,
            $user,
        );
    }

    private function modeFromDecision(AccessDecision $decision): PermissionDeniedMode
    {
        $mode = $decision->context['denied_mode'] ?? $this->defaultMode;
        if ($mode instanceof PermissionDeniedMode) {
            return $mode;
        }

        if (is_string($mode) && $mode !== '') {
            return PermissionDeniedMode::from($mode);
        }

        return $this->defaultMode;
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

    private function redirectTo(AccessDecision $decision): ?string
    {
        $redirectTo = $decision->context['redirect_to'] ?? $this->redirectTo;

        return is_string($redirectTo) && $redirectTo !== '' ? $redirectTo : null;
    }
}
