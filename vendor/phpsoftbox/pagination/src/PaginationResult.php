<?php

declare(strict_types=1);

namespace PhpSoftBox\Pagination;

use PhpSoftBox\Pagination\Contracts\PaginationResultInterface;

use function ceil;
use function http_build_query;
use function max;
use function min;
use function str_contains;

final readonly class PaginationResult implements PaginationResultInterface
{
    /**
     * @param list<mixed> $items
     * @param array<string, string|int|float|bool|null> $query
     */
    public function __construct(
        private array $items,
        private int $total,
        private int $page,
        private int $perPage,
        private string $path,
        private array $query = [],
        private ?string $fragment = null,
        private int $window = 2,
        private string $pageParam = 'page',
    ) {
    }

    public function data(): array
    {
        return $this->items;
    }

    public function links(): array
    {
        $links = [
            'first' => $this->urlForPage(1),
            'last'  => $this->urlForPage($this->lastPage()),
        ];

        $prev = $this->previousPage();
        if ($prev !== null) {
            $links['prev'] = $this->urlForPage($prev);
        }

        $next = $this->nextPage();
        if ($next !== null) {
            $links['next'] = $this->urlForPage($next);
        }

        return $links;
    }

    public function meta(): array
    {
        return [
            'current_page' => $this->page,
            'from'         => $this->from(),
            'last_page'    => $this->lastPage(),
            'links'        => $this->linkCollection(),
            'path'         => $this->path,
            'per_page'     => $this->perPage,
            'to'           => $this->to(),
            'total'        => $this->total,
        ];
    }

    public function toArray(): array
    {
        return [
            'data'  => $this->data(),
            'links' => $this->links(),
            'meta'  => $this->meta(),
        ];
    }

    private function from(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (($this->page - 1) * $this->perPage) + 1;
    }

    private function to(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return min($this->page * $this->perPage, $this->total);
    }

    private function lastPage(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }

    private function previousPage(): ?int
    {
        return $this->page > 1 ? $this->page - 1 : null;
    }

    private function nextPage(): ?int
    {
        return $this->page < $this->lastPage() ? $this->page + 1 : null;
    }

    /**
     * @return list<array{active:bool,label:string,url:string|null}>
     */
    private function linkCollection(): array
    {
        $links = [];

        $links[] = [
            'active' => false,
            'label'  => 'Previous',
            'url'    => $this->previousPage() !== null ? $this->urlForPage($this->previousPage()) : null,
        ];

        $start = max(1, $this->page - $this->window);
        $end   = min($this->lastPage(), $this->page + $this->window);

        for ($page = $start; $page <= $end; $page++) {
            $links[] = [
                'active' => $page === $this->page,
                'label'  => (string) $page,
                'url'    => $this->urlForPage($page),
            ];
        }

        $links[] = [
            'active' => false,
            'label'  => 'Next',
            'url'    => $this->nextPage() !== null ? $this->urlForPage($this->nextPage()) : null,
        ];

        return $links;
    }

    private function urlForPage(int $page): string
    {
        $query                   = $this->query;
        $query[$this->pageParam] = $page;

        $queryString = http_build_query($query);

        $separator = $queryString === '' ? '' : (str_contains($this->path, '?') ? '&' : '?');
        $url       = $this->path . $separator . $queryString;

        if ($this->fragment !== null && $this->fragment !== '') {
            $url .= '#' . $this->fragment;
        }

        return $url;
    }
}
