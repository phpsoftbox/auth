<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Redirect;

use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function is_string;
use function preg_match;
use function str_starts_with;
use function strtoupper;
use function trim;

final readonly class IntendedUrlStore
{
    /**
     * @param list<string> $excludePaths
     * @param list<string> $excludePrefixes
     */
    public function __construct(
        private SessionInterface $session,
        private string $key = 'auth.intended',
        private array $excludePaths = [],
        private array $excludePrefixes = [],
    ) {
    }

    public function remember(ServerRequestInterface $request): void
    {
        if ($this->session->has($this->key) || !$this->shouldStore($request)) {
            return;
        }

        $this->session->set($this->key, $this->url($request));
    }

    public function pull(string $fallback): string
    {
        $intended = $this->session->get($this->key);
        $this->session->forget($this->key);

        return is_string($intended) && $this->isLocalPath($intended) ? $intended : $fallback;
    }

    public function forget(): void
    {
        $this->session->forget($this->key);
    }

    private function shouldStore(ServerRequestInterface $request): bool
    {
        if (strtoupper($request->getMethod()) !== 'GET') {
            return false;
        }

        $path = $this->path($request);
        if (!$this->isLocalPath($path) || in_array($path, $this->excludePaths, true)) {
            return false;
        }

        foreach ($this->excludePrefixes as $prefix) {
            $prefix = $this->normalizePath($prefix);
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Только путь внутри сайта: `//evil.com/x` и `/\\evil.com` браузер понимает как адрес другого хоста.
     */
    private function isLocalPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_starts_with($url, '/\\')
            && preg_match('/[\x00-\x1F\x7F]/', $url) !== 1;
    }

    private function url(ServerRequestInterface $request): string
    {
        $uri   = $request->getUri();
        $path  = $this->path($request);
        $query = $uri->getQuery();

        return $query !== '' ? $path . '?' . $query : $path;
    }

    private function path(ServerRequestInterface $request): string
    {
        return $this->normalizePath($request->getUri()->getPath());
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }
}
