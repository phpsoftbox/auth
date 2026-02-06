<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;

final readonly class PermissionRequirement
{
    public string $permission;

    /**
     * @param list<PermissionCase> $cases
     */
    public function __construct(
        string|BackedEnum $permission = '',
        public ?string $subjectAttribute = null,
        public array $cases = [],
        public PermissionDeniedMode $deniedMode = PermissionDeniedMode::Forbidden,
        public PermissionRequirementMode $mode = PermissionRequirementMode::Single,
    ) {
        $this->permission = PermissionName::normalize($permission);
    }

    public static function single(string|BackedEnum $permission, ?string $subjectAttribute = null): self
    {
        return new self($permission, $subjectAttribute);
    }

    /**
     * @param list<PermissionCase> $cases
     */
    public static function any(array $cases, PermissionDeniedMode $deniedMode = PermissionDeniedMode::Forbidden): self
    {
        return new self(cases: $cases, deniedMode: $deniedMode, mode: PermissionRequirementMode::Any);
    }

    /**
     * @param list<PermissionCase> $cases
     */
    public static function all(array $cases, PermissionDeniedMode $deniedMode = PermissionDeniedMode::Forbidden): self
    {
        return new self(cases: $cases, deniedMode: $deniedMode, mode: PermissionRequirementMode::All);
    }

    public function isAny(): bool
    {
        return $this->mode === PermissionRequirementMode::Any && $this->cases !== [];
    }

    public function isAll(): bool
    {
        return $this->mode === PermissionRequirementMode::All && $this->cases !== [];
    }

    public function hasCases(): bool
    {
        return $this->isAny() || $this->isAll();
    }
}
