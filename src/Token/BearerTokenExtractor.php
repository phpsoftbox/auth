<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Token;

use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function strncasecmp;
use function substr;
use function trim;

final class BearerTokenExtractor implements TokenExtractorInterface
{
    /**
     * @param string $headerName Имя заголовка, из которого читается токен.
     * @param list<string> $queryParams Имена query-параметров для поиска токена.
     */
    public function __construct(
        private readonly string $headerName = 'Authorization',
        private readonly array $queryParams = ['access_token', 'token'],
    ) {
    }

    public function extract(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine($this->headerName);
        if ($header !== '' && strncasecmp($header, 'Bearer ', 7) === 0) {
            $token = trim((string) substr($header, 7));
            if ($token !== '') {
                return $token;
            }
        }

        $query = $request->getQueryParams();
        foreach ($this->queryParams as $param) {
            if (isset($query[$param])) {
                $value = $query[$param];
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
