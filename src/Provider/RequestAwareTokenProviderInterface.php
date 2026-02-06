<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Provider;

use PhpSoftBox\Auth\Contracts\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RequestAwareTokenProviderInterface extends TokenProviderInterface
{
    public function retrieveUserByTokenForRequest(string $token, ServerRequestInterface $request): ?UserInterface;
}
