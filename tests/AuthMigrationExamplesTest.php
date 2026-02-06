<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests;

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function dirname;
use function glob;

final class AuthMigrationExamplesTest extends TestCase
{
    /**
     * Проверяет, что migration examples компонента возвращают объекты миграций.
     */
    #[Test]
    public function migrationExamplesReturnMigrations(): void
    {
        $files = glob(dirname(__DIR__) . '/database/migrations/*.php');

        self::assertIsArray($files);
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $migration = require $file;

            self::assertInstanceOf(AbstractMigration::class, $migration);
        }
    }
}
