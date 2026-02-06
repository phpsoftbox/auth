<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use PhpSoftBox\Application\Exception\HttpExceptionInterface;
use PhpSoftBox\Router\Exception\MethodNotAllowedException;
use PhpSoftBox\Router\Exception\RouteNotFoundException;
use PhpSoftBox\Validator\Exception\ValidationException;
use Throwable;

use function method_exists;

abstract class AbstractExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        protected readonly bool $includeDetails = false,
    ) {
    }

    /**
     * @return array{status:int, headers: array<string, string|string[]>}
     */
    protected function resolveStatusAndHeaders(Throwable $exception): array
    {
        $status = 500;
        $headers = [];

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->statusCode();
            $headers = $exception->headers();
        } elseif (method_exists($exception, 'statusCode') && method_exists($exception, 'headers')) {
            $status = (int) $exception->statusCode();
            $headers = (array) $exception->headers();
        }

        if ($exception instanceof ValidationException) {
            $status = 422;
        }
        if ($exception instanceof RouteNotFoundException) {
            $status = 404;
        }
        if ($exception instanceof MethodNotAllowedException) {
            $status = 405;
            $headers = ['Allow' => $exception->allowedMethods()] + $headers;
        }

        return ['status' => $status, 'headers' => $headers];
    }

    protected function resolveMessage(Throwable $exception, int $status): string
    {
        if ($exception instanceof ValidationException) {
            return $exception->getMessage();
        }

        if (
            ($exception instanceof HttpExceptionInterface
                || (method_exists($exception, 'statusCode') && method_exists($exception, 'headers')))
            && $exception->getMessage() !== ''
        ) {
            return $exception->getMessage();
        }

        if ($status >= 500 && !$this->includeDetails) {
            return 'Internal Server Error';
        }

        return $exception->getMessage() !== '' ? $exception->getMessage() : 'Error';
    }
}
