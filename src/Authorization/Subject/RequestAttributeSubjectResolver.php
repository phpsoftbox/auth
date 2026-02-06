<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use PhpSoftBox\Auth\Authorization\AccessDecision;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

final readonly class RequestAttributeSubjectResolver implements SubjectResolverInterface
{
    public function __construct(
        private string $attribute,
    ) {
    }

    public function resolve(ServerRequestInterface $request): mixed
    {
        $missing = new stdClass();

        $value = $request->getAttribute($this->attribute, $missing);
        if ($value === $missing) {
            throw new SubjectResolutionException(AccessDecision::deny(
                reason: 'Request attribute is missing: ' . $this->attribute,
                context: ['request_attribute' => $this->attribute],
            ));
        }

        return $value;
    }
}
