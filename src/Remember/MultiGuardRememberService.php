<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Remember;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

use function is_int;
use function is_string;
use function trim;

final class MultiGuardRememberService
{
    /**
     * @var array<string, RememberGuardConfig>
     */
    private readonly array $guards;

    /**
     * @param array<string, RememberGuardConfig> $guards
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly int $ttlDays,
        array $guards,
    ) {
        $this->guards = $this->normalizeGuards($guards);
    }

    public function restore(string $guard, ServerRequestInterface $request): bool
    {
        $config = $this->guard($guard);
        if (!$this->enabled || $config->guard->user($request) !== null) {
            return false;
        }

        $token = $config->extractor->extract($request);
        if ($token === null) {
            return false;
        }

        $record = $config->store->findValid($token, $request);
        if ($record === null) {
            $config->cookies->queueForget($request);

            return false;
        }

        $user = $config->users->retrieveById($record->userId);
        if ($user === null) {
            $config->store->revoke($token);
            $config->cookies->queueForget($request);

            return false;
        }

        $config->guard->login($user);

        return true;
    }

    public function issue(string $guard, int|string $userId, ServerRequestInterface $request): void
    {
        $config = $this->guard($guard);
        if (!$this->enabled || !$this->isValidUserId($userId)) {
            return;
        }

        $expiresAt = $this->expiresAt();
        $issued    = $config->store->issue(
            userId: $userId,
            expiresAt: $expiresAt,
            metadata: ['area' => $guard] + $config->metadata,
            request: $request,
        );

        $config->cookies->queue($issued->token, $expiresAt, $request);
    }

    public function forget(string $guard, ServerRequestInterface $request): void
    {
        $config = $this->guard($guard);
        if (!$this->enabled) {
            return;
        }

        $token = $config->extractor->extract($request);
        if ($token !== null) {
            $config->store->revoke($token);
        }

        $config->cookies->queueForget($request);
    }

    private function guard(string $guard): RememberGuardConfig
    {
        $guard = trim($guard);
        if ($guard === '' || !isset($this->guards[$guard])) {
            throw new InvalidArgumentException("Remember guard is not configured: {$guard}");
        }

        return $this->guards[$guard];
    }

    /**
     * @param array<string, RememberGuardConfig> $guards
     * @return array<string, RememberGuardConfig>
     */
    private function normalizeGuards(array $guards): array
    {
        $normalized = [];
        foreach ($guards as $name => $config) {
            $name = trim((string) $name);
            if ($name === '') {
                throw new InvalidArgumentException('Remember guard name must not be empty.');
            }

            if (!$config instanceof RememberGuardConfig) {
                throw new InvalidArgumentException("Remember guard config must be an instance of RememberGuardConfig: {$name}");
            }

            $normalized[$name] = $config;
        }

        return $normalized;
    }

    private function isValidUserId(int|string $userId): bool
    {
        if (is_int($userId)) {
            return $userId > 0;
        }

        return is_string($userId) && trim($userId) !== '';
    }

    private function expiresAt(): DateTimeImmutable
    {
        $days = $this->ttlDays > 0 ? $this->ttlDays : 30;

        return new DateTimeImmutable()->add(new DateInterval('P' . $days . 'D'));
    }
}
