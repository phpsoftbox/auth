<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Guard;

use PhpSoftBox\Auth\Provider\TokenProviderInterface;
use PhpSoftBox\Auth\Provider\UserProviderInterface;
use PhpSoftBox\Auth\Token\BearerTokenExtractor;
use PhpSoftBox\Auth\Token\TokenExtractorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TokenGuard implements GuardInterface
{
    private TokenExtractorInterface $extractor;

    public function __construct(
        private readonly TokenProviderInterface $tokens,
        private readonly UserProviderInterface $users,
        ?TokenExtractorInterface $extractor = null,
    ) {
        $this->extractor = $extractor ?? new BearerTokenExtractor();
    }

    public function user(ServerRequestInterface $request): mixed
    {
        $token = $this->extractor->extract($request);
        if ($token === null || $token === '') {
            return null;
        }

        $userId = $this->tokens->retrieveUserIdByToken($token);
        if ($userId === null) {
            return null;
        }

        return $this->users->retrieveById($userId);
    }
}
