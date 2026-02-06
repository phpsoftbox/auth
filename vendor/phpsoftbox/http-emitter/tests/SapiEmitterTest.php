<?php

declare(strict_types=1);

namespace PhpSoftBox\Http\Emitter\Tests;

use PhpSoftBox\Http\Emitter\SapiEmitter;
use PhpSoftBox\Http\Message\Response;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function function_exists;
use function header_remove;
use function headers_list;
use function ob_get_clean;
use function ob_start;

final class SapiEmitterTest extends TestCase
{
    /**
     * Проверяем, что эмиттер пишет тело ответа и устанавливает заголовки.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmitWritesBodyAndHeaders(): void
    {
        if (function_exists('header_remove')) {
            header_remove();
        }

        $response = new Response(201, ['X-Test' => 'ok'], 'payload');

        ob_start();
        (new SapiEmitter())->emit($response);
        $output = ob_get_clean();

        $this->assertSame('payload', $output);

        $headers = headers_list();
        $this->assertContains('X-Test: ok', $headers);
    }
}
