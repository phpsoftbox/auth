<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    public function emit(ResponseInterface $response): void;
}
