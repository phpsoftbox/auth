<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Authorization;

use PhpSoftBox\Auth\Authorization\PhpFileRoleDefinitionProvider;
use PhpSoftBox\Auth\Authorization\RoleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_is_list;
use function array_keys;
use function in_array;

#[CoversClass(PhpFileRoleDefinitionProvider::class)]
final class PhpFileRoleDefinitionProviderTest extends TestCase
{
    /**
     * Проверяет, что провайдер загружает роли и пермишены из нескольких файлов.
     */
    #[Test]
    public function loadsDefinitionsFromDirectory(): void
    {
        $provider = new PhpFileRoleDefinitionProvider(__DIR__ . '/../Fixtures/authorization');

        $set = $provider->load();

        self::assertCount(1, $set->roles);
        self::assertInstanceOf(RoleDefinition::class, $set->roles[0]);
        self::assertSame('alpha', $set->roles[0]->name);

        $names = $this->normalizePermissions($set->permissions);
        self::assertTrue(in_array('alpha.view', $names, true));
    }

    /**
     * @param array<string, string|null>|list<string> $permissions
     * @return list<string>
     */
    private function normalizePermissions(array $permissions): array
    {
        if (array_is_list($permissions)) {
            return $permissions;
        }

        return array_keys($permissions);
    }
}
