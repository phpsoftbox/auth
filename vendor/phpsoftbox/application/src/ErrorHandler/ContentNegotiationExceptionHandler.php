<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function str_contains;
use function strtolower;

final class ContentNegotiationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly ExceptionHandlerInterface $jsonHandler,
        private readonly ExceptionHandlerInterface $htmlHandler,
    ) {
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        if ($this->wantsJson($request)) {
            return $this->jsonHandler->handle($exception, $request);
        }

        return $this->htmlHandler->handle($exception, $request);
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        if ($request->getHeaderLine('X-Inertia') !== '') {
            return true;
        }

        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower($request->getHeaderLine('Accept'));

        if ($accept === '') {
            return false;
        }

        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json')
            || str_contains($accept, '/json');
    }
}
