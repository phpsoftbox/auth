<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\Exception;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Not Found', array $headers = [])
    {
        parent::__construct(404, $message, $headers);
    }
}
