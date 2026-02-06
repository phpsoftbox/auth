<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Auth\Redirect\IntendedUrlStore;
use PhpSoftBox\Http\Message\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntendedUrlStore::class)]
final class IntendedUrlStoreTest extends TestCase
{
    #[Test]
    public function rememberStoresGetPathWithQuery(): void
    {
        $session = new TrackingSession();

        $store = new IntendedUrlStore($session);

        $store->remember(new ServerRequest('GET', 'https://example.test/users?page=2'));

        self::assertSame('/users?page=2', $session->get('auth.intended'));
    }

    #[Test]
    public function rememberSkipsNonGetAndExcludedPaths(): void
    {
        $session = new TrackingSession();

        $store = new IntendedUrlStore(
            $session,
            excludePaths: ['/invite'],
            excludePrefixes: ['/login', '/password/reset'],
        );

        $store->remember(new ServerRequest('POST', 'https://example.test/users'));
        $store->remember(new ServerRequest('GET', 'https://example.test/login'));
        $store->remember(new ServerRequest('GET', 'https://example.test/password/reset/confirm'));
        $store->remember(new ServerRequest('GET', 'https://example.test/invite'));

        self::assertFalse($session->has('auth.intended'));
    }

    #[Test]
    public function rememberDoesNotOverrideExistingValue(): void
    {
        $session = new TrackingSession();

        $session->set('auth.intended', '/first');

        $store = new IntendedUrlStore($session);

        $store->remember(new ServerRequest('GET', 'https://example.test/second'));

        self::assertSame('/first', $session->get('auth.intended'));
    }

    #[Test]
    public function pullReturnsAndForgetsStoredValue(): void
    {
        $session = new TrackingSession();

        $session->set('tenant.auth.intended', '/tasks');

        $store = new IntendedUrlStore($session, key: 'tenant.auth.intended');

        self::assertSame('/tasks', $store->pull('/'));
        self::assertFalse($session->has('tenant.auth.intended'));
    }

    #[Test]
    public function pullReturnsFallbackWhenStoredValueIsMissing(): void
    {
        $store = new IntendedUrlStore(new TrackingSession());

        self::assertSame('/fallback', $store->pull('/fallback'));
    }
}
