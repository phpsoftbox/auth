<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization;

use BackedEnum;
use InvalidArgumentException;
use PhpSoftBox\Auth\Authorization\Subject\SubjectResolverInterface;

use function is_string;

final readonly class PermissionCase
{
    public string $permission;

    public function __construct(
        string|BackedEnum $permission,
        public ?PermissionCaseSubjectTypeEnum $subjectType = null,
        public string|SubjectResolverInterface|null $subject = null,
    ) {
        $this->permission = PermissionName::normalize($permission);
    }

    public static function make(string|BackedEnum $permission): self
    {
        return new self($permission);
    }

    public static function routeParam(string|BackedEnum $permission, string $param): self
    {
        return new self($permission, PermissionCaseSubjectTypeEnum::RouteParam, $param);
    }

    public static function ownership(string|BackedEnum $permission, string $routeParam): self
    {
        return new self($permission, PermissionCaseSubjectTypeEnum::Ownership, $routeParam);
    }

    public static function requestAttribute(string|BackedEnum $permission, string $attribute): self
    {
        return new self($permission, PermissionCaseSubjectTypeEnum::RequestAttribute, $attribute);
    }

    public static function custom(string|BackedEnum $permission, string|SubjectResolverInterface $resolver): self
    {
        return new self($permission, PermissionCaseSubjectTypeEnum::Custom, $resolver);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $permission = PermissionName::from($data['permission'] ?? null);
        if ($permission === null) {
            throw new InvalidArgumentException('Permission case must contain non-empty permission.');
        }

        $subjectType = $data['subjectType'] ?? $data['subject_type'] ?? null;
        if (is_string($subjectType) && $subjectType !== '') {
            $subjectType = PermissionCaseSubjectTypeEnum::from($subjectType);
        }

        if (!$subjectType instanceof PermissionCaseSubjectTypeEnum && $subjectType !== null) {
            throw new InvalidArgumentException('Permission case subject type must be null, string or PermissionCaseSubjectTypeEnum.');
        }

        $subject = $data['subject'] ?? null;
        if (!$subject instanceof SubjectResolverInterface && !is_string($subject) && $subject !== null) {
            throw new InvalidArgumentException('Permission case subject must be null, string or SubjectResolverInterface.');
        }

        return new self($permission, $subjectType, $subject);
    }
}
