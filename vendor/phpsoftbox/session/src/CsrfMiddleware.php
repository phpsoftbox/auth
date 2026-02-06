<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use PhpSoftBox\Application\Exception\HttpException;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function bin2hex;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function random_bytes;
use function strtoupper;

final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly string $sessionKey = 'csrf_token',
        private readonly string $inputKey = '_token',
        private readonly string $headerKey = 'X-CSRF-Token',
        private readonly array $methods = ['POST', 'PUT', 'PATCH', 'DELETE'],
        private readonly bool $regenerate = false,
        private readonly string $attribute = 'csrf_token',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();

        $token = $this->session->get($this->sessionKey);
        if (!is_string($token) || $token === '') {
            $token = $this->generateToken();
            $this->session->set($this->sessionKey, $token);
        }

        $request = $request->withAttribute($this->attribute, $token);

        $method = strtoupper($request->getMethod());
        if (in_array($method, $this->methods, true)) {
            $provided = $this->extractToken($request);
            if ($provided === null || !hash_equals($token, $provided)) {
                throw new HttpException(419, 'CSRF token mismatch.');
            }

            if ($this->regenerate) {
                $token = $this->generateToken();
                $this->session->set($this->sessionKey, $token);
                $request = $request->withAttribute($this->attribute, $token);
            }
        }

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine($this->headerKey);
        if ($header !== '') {
            return $header;
        }

        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[$this->inputKey]) && is_string($body[$this->inputKey])) {
            return $body[$this->inputKey];
        }

        return null;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
