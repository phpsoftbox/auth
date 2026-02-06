<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Message;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;

use function array_keys;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;
use function ucwords;

use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

final readonly class ServerRequestCreator
{
    public function __construct(
        private ServerRequestFactoryInterface $serverRequestFactory = new ServerRequestFactory(),
        private UriFactoryInterface $uriFactory = new UriFactory(),
        private StreamFactoryInterface $streamFactory = new StreamFactory(),
        private UploadedFileFactoryInterface $uploadedFileFactory = new UploadedFileFactory(),
    ) {
    }

    /**
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     */
    public function fromGlobals(
        ?array $server = null,
        ?array $query = null,
        ?array $parsedBody = null,
        ?array $cookies = null,
        ?array $files = null,
    ): ServerRequestInterface {
        $server     = $server ?? $_SERVER;
        $query      = $query ?? $_GET;
        $parsedBody = $parsedBody ?? ($_POST !== [] ? $_POST : null);
        $cookies    = $cookies ?? $_COOKIE;
        $files      = $files ?? $_FILES;

        $method = (string) ($server['REQUEST_METHOD'] ?? 'GET');

        $uri     = $this->createUriFromGlobals($server);
        $request = $this->serverRequestFactory->createServerRequest($method, $uri, $server);

        foreach ($this->marshalHeaders($server) as $name => $values) {
            $request = $request->withHeader($name, $values);
        }

        $request = $request->withQueryParams($query)->withCookieParams($cookies);

        if ($parsedBody !== null) {
            $request = $request->withParsedBody($parsedBody);
        }

        $body    = $this->streamFactory->createStreamFromFile('php://input', 'r');
        $request = $request->withBody($body);

        if ($files !== []) {
            $request = $request->withUploadedFiles($this->normalizeFiles($files));
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function createUriFromGlobals(array $server): Uri
    {
        $scheme     = $this->detectScheme($server);
        $hostHeader = (string) ($server['HTTP_HOST'] ?? '');

        $host = $hostHeader;
        $port = null;
        if ($hostHeader !== '' && str_contains($hostHeader, ':')) {
            [$host, $portStr] = explode(':', $hostHeader, 2);
            $port             = (int) $portStr;
        }

        if ($host === '') {
            $host = (string) ($server['SERVER_NAME'] ?? $server['SERVER_ADDR'] ?? '');
        }

        if ($port === null && isset($server['SERVER_PORT'])) {
            $port = (int) $server['SERVER_PORT'];
        }

        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
        $parts      = parse_url($requestUri) ?: [];
        $path       = (string) ($parts['path'] ?? '/');
        $query      = (string) ($parts['query'] ?? ($server['QUERY_STRING'] ?? ''));

        $uri = $this->uriFactory->createUri();
        $uri = $uri->withScheme($scheme)
            ->withHost($host)
            ->withPort($this->normalizePort($scheme, $port))
            ->withPath($path)
            ->withQuery($query);

        return $uri;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function detectScheme(array $server): string
    {
        $https = $server['HTTPS'] ?? null;
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }

        $scheme = $server['REQUEST_SCHEME'] ?? null;
        if (is_string($scheme) && $scheme !== '') {
            return strtolower($scheme);
        }

        $forwarded = $server['HTTP_X_FORWARDED_PROTO'] ?? null;
        if (is_string($forwarded) && $forwarded !== '') {
            $parts = explode(',', $forwarded, 2);

            return strtolower(trim($parts[0]));
        }

        return 'http';
    }

    private function normalizePort(string $scheme, ?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if ($scheme === 'https' && $port === 443) {
            return null;
        }

        if ($scheme === 'http' && $port === 80) {
            return null;
        }

        return $port;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string[]>
     */
    private function marshalHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            if (str_starts_with($name, 'HTTP_')) {
                $header           = $this->normalizeHeaderName(substr($name, 5));
                $headers[$header] = [(string) $value];
                continue;
            }

            if (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $header           = $this->normalizeHeaderName($name);
                $headers[$header] = [(string) $value];
            }
        }

        return $headers;
    }

    private function normalizeHeaderName(string $name): string
    {
        $name = strtolower(str_replace('_', ' ', $name));
        $name = ucwords($name);

        return str_replace(' ', '-', $name);
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, UploadedFileInterface|array>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFileInterface) {
                $normalized[$key] = $file;
                continue;
            }

            if (is_array($file) && isset($file['tmp_name'])) {
                $normalized[$key] = $this->createUploadedFileFromSpec($file);
                continue;
            }

            if (is_array($file)) {
                $normalized[$key] = $this->normalizeFiles($file);
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function createUploadedFileFromSpec(array $spec): UploadedFileInterface|array
    {
        if (is_array($spec['tmp_name'])) {
            $files = [];
            $keys  = array_keys($spec['tmp_name']);
            foreach ($keys as $idx) {
                $files[$idx] = $this->createUploadedFileFromSpec([
                    'tmp_name' => $spec['tmp_name'][$idx] ?? '',
                    'size'     => $spec['size'][$idx] ?? null,
                    'error'    => $spec['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
                    'name'     => $spec['name'][$idx] ?? null,
                    'type'     => $spec['type'][$idx] ?? null,
                ]);
            }

            return $files;
        }

        $tmpName = (string) ($spec['tmp_name'] ?? '');
        $size    = isset($spec['size']) ? (int) $spec['size'] : null;
        $error   = isset($spec['error']) ? (int) $spec['error'] : UPLOAD_ERR_OK;
        $name    = isset($spec['name']) ? (string) $spec['name'] : null;
        $type    = isset($spec['type']) ? (string) $spec['type'] : null;

        $stream = $tmpName !== '' && $error === UPLOAD_ERR_OK
            ? $this->streamFactory->createStreamFromFile($tmpName, 'r')
            : $this->streamFactory->createStream('');

        return $this->uploadedFileFactory->createUploadedFile($stream, $size, $error, $name, $type);
    }
}
