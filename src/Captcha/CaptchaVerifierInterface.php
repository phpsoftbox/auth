<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Captcha;

use Psr\Http\Message\ServerRequestInterface;

interface CaptchaVerifierInterface
{
    public function isEnabled(): bool;

    public function siteKey(): ?string;

    public function verify(?string $token, ServerRequestInterface $request): bool;
}
