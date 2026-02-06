<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

use function array_shift;

final class RecordingHttpClient implements ClientInterface
{
    public int $callCount                 = 0;
    public ?RequestInterface $lastRequest = null;
    private ?Throwable $throwable         = null;

    /** @var list<ResponseInterface> */
    private array $responses = [];

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = $responses;
    }

    public function queueResponse(ResponseInterface $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function failWith(Throwable $throwable): self
    {
        $this->throwable = $throwable;

        return $this;
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->callCount++;
        $this->lastRequest = $request;

        if ($this->throwable !== null) {
            throw $this->throwable;
        }

        $response = array_shift($this->responses);
        if ($response === null) {
            throw new RuntimeException('No queued response configured for RecordingHttpClient.');
        }

        return $response;
    }
}
