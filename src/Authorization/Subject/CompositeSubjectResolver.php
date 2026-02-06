<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Subject;

use Psr\Http\Message\ServerRequestInterface;

final readonly class CompositeSubjectResolver implements SubjectResolverInterface
{
    /**
     * @param array<string, SubjectResolverInterface> $resolvers
     */
    public function __construct(
        private array $resolvers,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(ServerRequestInterface $request): array
    {
        $subject = [];
        foreach ($this->resolvers as $name => $resolver) {
            $subject[$name] = $resolver->resolve($request);
        }

        return $subject;
    }
}
