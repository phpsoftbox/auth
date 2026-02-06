<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter;

use Psr\Http\Message\ResponseInterface;

use function header;
use function headers_sent;
use function sprintf;

final class SapiEmitter implements EmitterInterface
{
    public function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            $statusLine = sprintf(
                'HTTP/%s %d %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase(),
            );

            header($statusLine, true, $response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
        }

        echo (string) $response->getBody();
    }
}
