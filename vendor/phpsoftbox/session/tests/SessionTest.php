<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Session\ArraySessionStore;
use PhpSoftBox\Session\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    /**
     * Проверяем базовые операции set/get/has/forget.
     */
    public function testBasicOperations(): void
    {
        $session = new Session(new ArraySessionStore());
        $session->start();

        $session->set('a', 1);

        $this->assertTrue($session->has('a'));
        $this->assertSame(1, $session->get('a'));

        $session->forget('a');

        $this->assertFalse($session->has('a'));
    }

    /**
     * Проверяем flash-данные.
     */
    public function testFlashData(): void
    {
        $store = new ArraySessionStore();
        $session = new Session($store);
        $session->start();

        $session->flash('notice', 'ok');
        $this->assertSame('ok', $session->getFlash('notice'));

        $session->save();

        $session2 = new Session($store);
        $session2->start();

        $this->assertSame('ok', $session2->getFlash('notice'));

        $session2->save();

        $session3 = new Session($store);
        $session3->start();

        $this->assertNull($session3->getFlash('notice'));
    }

    /**
     * Проверяем pull() и destroy().
     */
    public function testPullAndDestroy(): void
    {
        $store = new ArraySessionStore();
        $session = new Session($store);
        $session->start();

        $session->set('key', 'value');

        $this->assertSame('value', $session->pull('key'));
        $this->assertNull($session->get('key'));

        $session->set('a', 1);
        $session->destroy();

        $this->assertSame([], $session->all());
    }
}
