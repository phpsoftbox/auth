<?php

declare(strict_types=1);

namespace PhpSoftBox\Pagination\Tests;

use PhpSoftBox\Pagination\PaginationResult;
use PhpSoftBox\Pagination\Paginator;
use PhpSoftBox\Pagination\RequestPaginationContextResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(Paginator::class)]
#[CoversClass(PaginationResult::class)]
final class PaginationResultTest extends TestCase
{
    /**
     * Проверяет структуру результата пагинации.
     */
    #[Test]
    public function resultHasDataLinksAndMeta(): void
    {
        $paginator = new Paginator(perPage: 2)
            ->path('/users')
            ->appends(['status' => 'active'])
            ->fragment('list')
            ->window(1);

        $result = $paginator->make(
            items: [['id' => 1], ['id' => 2]],
            total: 5,
            page: 2,
        );

        self::assertSame(
            [
                'data'  => [['id' => 1], ['id' => 2]],
                'links' => [
                    'first' => '/users?status=active&page=1#list',
                    'last'  => '/users?status=active&page=3#list',
                    'prev'  => '/users?status=active&page=1#list',
                    'next'  => '/users?status=active&page=3#list',
                ],
                'meta' => [
                    'current_page' => 2,
                    'from'         => 3,
                    'last_page'    => 3,
                    'links'        => [
                        [
                            'active' => false,
                            'label'  => 'Previous',
                            'url'    => '/users?status=active&page=1#list',
                        ],
                        [
                            'active' => false,
                            'label'  => '1',
                            'url'    => '/users?status=active&page=1#list',
                        ],
                        [
                            'active' => true,
                            'label'  => '2',
                            'url'    => '/users?status=active&page=2#list',
                        ],
                        [
                            'active' => false,
                            'label'  => '3',
                            'url'    => '/users?status=active&page=3#list',
                        ],
                        [
                            'active' => false,
                            'label'  => 'Next',
                            'url'    => '/users?status=active&page=3#list',
                        ],
                    ],
                    'path'     => '/users',
                    'per_page' => 2,
                    'to'       => 4,
                    'total'    => 5,
                ],
            ],
            $result->toArray(),
        );
    }

    /**
     * Проверяет автоматическое определение path и perPage из запроса.
     */
    #[Test]
    public function requestProvidesPathAndPerPage(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/users');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getQueryParams')->willReturn(['per_page' => '3']);

        $resolver = new RequestPaginationContextResolver($request, perPageParam: 'per_page', perPageMax: 100);

        $paginator = new Paginator()
            ->resolver($resolver)
            ->window(0);

        $result = $paginator->make(items: [['id' => 1]], total: 5);

        self::assertSame(
            [
                'data'  => [['id' => 1]],
                'links' => [
                    'first' => '/users?per_page=3&page=1',
                    'last'  => '/users?per_page=3&page=2',
                    'next'  => '/users?per_page=3&page=2',
                ],
                'meta' => [
                    'current_page' => 1,
                    'from'         => 1,
                    'last_page'    => 2,
                    'links'        => [
                        [
                            'active' => false,
                            'label'  => 'Previous',
                            'url'    => null,
                        ],
                        [
                            'active' => true,
                            'label'  => '1',
                            'url'    => '/users?per_page=3&page=1',
                        ],
                        [
                            'active' => false,
                            'label'  => 'Next',
                            'url'    => '/users?per_page=3&page=2',
                        ],
                    ],
                    'path'     => '/users',
                    'per_page' => 3,
                    'to'       => 3,
                    'total'    => 5,
                ],
            ],
            $result->toArray(),
        );
    }
}
