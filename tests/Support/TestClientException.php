<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class TestClientException extends RuntimeException implements ClientExceptionInterface
{
}
