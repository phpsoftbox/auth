<?php

declare(strict_types=1);

namespace PhpSoftBox\Pagination;

use PhpSoftBox\Pagination\Contracts\PaginationContextResolverInterface;

use function array_replace;
use function max;

final class Paginator
{
    /**
     * @var array<string, string|int|float|bool|null>
     */
    private array $query = [];

    private string $path      = '';
    private ?string $fragment = null;
    private int $window       = 2;
    private string $pageParam = 'page';
    private ?PaginationContextResolverInterface $resolver;

    public function __construct(
        private int $perPage = 15,
        ?PaginationContextResolverInterface $resolver = null,
    ) {
        $this->perPage  = max(1, $this->perPage);
        $this->resolver = $resolver;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function contextResolver(): ?PaginationContextResolverInterface
    {
        return $this->resolver;
    }

    /**
     * Устанавливает резолвер контекста пагинации.
     */
    public function resolver(PaginationContextResolverInterface $resolver): self
    {
        $clone           = clone $this;
        $clone->resolver = $resolver;

        return $clone;
    }

    /**
     * Базовый путь для ссылок.
     */
    public function path(string $path): self
    {
        $clone       = clone $this;
        $clone->path = $path;

        return $clone;
    }

    /**
     * Добавляет query-параметры к ссылкам.
     *
     * @param array<string, string|int|float|bool|null> $query
     */
    public function appends(array $query): self
    {
        $clone        = clone $this;
        $clone->query = array_replace($this->query, $query);

        return $clone;
    }

    /**
     * Добавляет фрагмент к ссылкам.
     */
    public function fragment(string $fragment): self
    {
        $clone           = clone $this;
        $clone->fragment = $fragment;

        return $clone;
    }

    /**
     * Настраивает размер окна ссылок.
     */
    public function window(int $window): self
    {
        $clone         = clone $this;
        $clone->window = max(0, $window);

        return $clone;
    }

    /**
     * Настраивает имя query-параметра для номера страницы.
     */
    public function pageParam(string $param): self
    {
        $clone            = clone $this;
        $clone->pageParam = $param;

        return $clone;
    }

    /**
     * Создаёт DTO пагинации.
     *
     * @param iterable<mixed> $items
     */
    public function make(iterable $items, int $total, ?int $page = null, ?int $perPage = null): PaginationResult
    {
        $list = [];
        foreach ($items as $item) {
            $list[] = $item;
        }

        $resolver = $this->resolver;
        $query    = $resolver?->query() ?? [];
        $query    = array_replace($query, $this->query);

        $path     = $this->path !== '' ? $this->path : ($resolver?->path() ?? '');
        $fragment = $this->fragment ?? $resolver?->fragment();

        $pageValue = $page ?? $resolver?->page() ?? 1;
        $pageValue = max(1, $pageValue);

        $perPageValue = $perPage ?? $resolver?->perPage() ?? $this->perPage;
        $perPageValue = max(1, $perPageValue);

        return new PaginationResult(
            items: $list,
            total: max(0, $total),
            page: $pageValue,
            perPage: $perPageValue,
            path: $path,
            query: $query,
            fragment: $fragment,
            window: $this->window,
            pageParam: $this->pageParam,
        );
    }
}
