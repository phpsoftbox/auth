<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Redirect;

use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function in_array;
use function is_string;
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
        if (is_string($intended) && trim($intended) !== '') {
            $this->session->forget($this->key);

            return $intended;
        }

        return $fallback;
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
        if (in_array($path, $this->excludePaths, true)) {
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
