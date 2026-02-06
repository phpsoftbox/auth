<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Middleware;

use InvalidArgumentException;

use function trim;

final readonly class AreaAccessRule
{
    public function __construct(
        public string $area,
        public ?string $guard = null,
        public ?string $permission = null,
        public AreaAccessDeniedMode $deniedMode = AreaAccessDeniedMode::Forbidden,
        public ?string $redirectTo = null,
        public string $userAttribute = 'user',
        public string $areaAttribute = '_area',
    ) {
        if (trim($this->area) === '') {
            throw new InvalidArgumentException('Area access rule area must not be empty.');
        }

        if ($this->deniedMode === AreaAccessDeniedMode::Redirect && trim((string) $this->redirectTo) === '') {
            throw new InvalidArgumentException('Redirect target must be configured for redirect denied mode.');
        }
    }
}
