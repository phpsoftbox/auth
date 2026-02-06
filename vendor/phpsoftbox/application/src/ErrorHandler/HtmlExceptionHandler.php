<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use PhpSoftBox\Validator\Exception\ValidationException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

use function htmlspecialchars;
use function is_array;
use function implode;
use function nl2br;
use function sprintf;

final class HtmlExceptionHandler extends AbstractExceptionHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        bool $includeDetails = false,
    ) {
        parent::__construct($includeDetails);
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        ['status' => $status, 'headers' => $headers] = $this->resolveStatusAndHeaders($exception);

        $message = $this->resolveMessage($exception, $status);
        $body = $this->renderHtml($status, $message, $exception);

        $stream = $this->streamFactory->createStream($body);
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, is_array($value) ? $value : (string) $value);
        }

        return $response->withBody($stream);
    }

    private function renderHtml(int $status, string $message, Throwable $exception): string
    {
        $details = '';

        if ($exception instanceof ValidationException) {
            $list = [];
            foreach ($exception->errors() as $field => $messages) {
                $list[] = sprintf('<li><strong>%s</strong>: %s</li>', htmlspecialchars($field), htmlspecialchars(implode(', ', $messages)));
            }

            $details = '<ul>' . implode('', $list) . '</ul>';
        } elseif ($this->includeDetails) {
            $details = sprintf(
                '<pre>%s</pre>',
                nl2br(htmlspecialchars($exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine()))
            );
        }

        $message = htmlspecialchars($message);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$status}</title>
</head>
<body>
    <h1>{$status}</h1>
    <p>{$message}</p>
    {$details}
</body>
</html>
HTML;
    }
}
