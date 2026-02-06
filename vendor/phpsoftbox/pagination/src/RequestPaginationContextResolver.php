<?php

declare(strict_types=1);

namespace PhpSoftBox\Pagination;

use PhpSoftBox\Pagination\Contracts\PaginationContextResolverInterface;
use Psr\Http\Message\ServerRequestInterface;

use function filter_var;
use function is_int;
use function is_string;
use function max;
use function min;

use const FILTER_VALIDATE_INT;

final readonly class RequestPaginationContextResolver implements PaginationContextResolverInterface
{
    /**
     * @param string $pageParam Имя query-параметра страницы.
     * @param string|null $perPageParam Имя query-параметра perPage (null — отключено).
     */
    public function __construct(
        private ServerRequestInterface $request,
        private string $pageParam = 'page',
        private ?string $perPageParam = null,
        private int $perPageMin = 1,
        private ?int $perPageMax = null,
    ) {
    }

    public function path(): ?string
    {
        return $this->request->getUri()->getPath();
    }

    public function fragment(): ?string
    {
        $fragment = $this->request->getUri()->getFragment();

        return $fragment === '' ? null : $fragment;
    }

    public function query(): array
    {
        return $this->request->getQueryParams();
    }

    public function page(): ?int
    {
        return $this->extractInt($this->pageParam);
    }

    public function perPage(): ?int
    {
        if ($this->perPageParam === null) {
            return null;
        }

        return $this->extractInt($this->perPageParam, min: $this->perPageMin, max: $this->perPageMax);
    }

    private function extractInt(string $key, int $min = 1, ?int $max = null): ?int
    {
        $value = $this->request->getQueryParams()[$key] ?? null;

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        if ($filtered === false) {
            return null;
        }

        $filtered = max($min, $filtered);
        if ($max !== null) {
            $filtered = min($max, $filtered);
        }

        return $filtered;
    }
}
