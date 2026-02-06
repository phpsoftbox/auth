<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Authorization\Store\Database;

use PhpSoftBox\Auth\Authorization\Store\UserRoleStoreInterface;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use Psr\SimpleCache\CacheInterface;

use function is_array;

final class DatabaseUserRoleStore implements UserRoleStoreInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly string $connectionName = 'default',
        private readonly string $rolesTable = 'roles',
        private readonly string $userRolesTable = 'user_roles',
        private readonly ?CacheInterface $cache = null,
        private readonly string $cachePrefix = 'auth.user_roles',
        private readonly int $cacheTtlSeconds = 300,
    ) {
    }

    public function listRoleIdsByUserId(int $userId): array
    {
        $cacheKey = $this->cacheKey($userId, 'ids');
        if ($this->cache instanceof CacheInterface) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $conn = $this->connections->read($this->connectionName);
        $rows = $conn->fetchAll(
            "SELECT role_id FROM {$this->userRolesTable} WHERE user_id = :user_id",
            ['user_id' => $userId],
        );

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['role_id'];
        }

        $this->cache?->set($cacheKey, $ids, $this->cacheTtlSeconds);

        return $ids;
    }

    public function listRoleNamesByUserId(int $userId): array
    {
        $cacheKey = $this->cacheKey($userId, 'names');
        if ($this->cache instanceof CacheInterface) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $conn = $this->connections->read($this->connectionName);
        $rows = $conn->fetchAll(
            "SELECT r.name FROM {$this->userRolesTable} ur JOIN {$this->rolesTable} r ON r.id = ur.role_id WHERE ur.user_id = :user_id ORDER BY r.name",
            ['user_id' => $userId],
        );

        $names = [];
        foreach ($rows as $row) {
            $names[] = (string) $row['name'];
        }

        $this->cache?->set($cacheKey, $names, $this->cacheTtlSeconds);

        return $names;
    }

    public function attach(int $userId, int $roleId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->insert($this->userRolesTable, [
                'user_id' => $userId,
                'role_id' => $roleId,
            ])
            ->execute();

        $this->forgetCache($userId);
    }

    public function detach(int $userId, int $roleId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->delete($this->userRolesTable)
            ->where('user_id = :user_id', ['user_id' => $userId])
            ->where('role_id = :role_id', ['role_id' => $roleId])
            ->execute();

        $this->forgetCache($userId);
    }

    public function detachAll(int $userId): void
    {
        $conn = $this->connections->write($this->connectionName);
        $conn->query()
            ->delete($this->userRolesTable)
            ->where('user_id = :user_id', ['user_id' => $userId])
            ->execute();

        $this->forgetCache($userId);
    }

    private function cacheKey(int $userId, string $suffix): string
    {
        return $this->cachePrefix . '.' . $suffix . '.' . $userId;
    }

    private function forgetCache(int $userId): void
    {
        if (!$this->cache instanceof CacheInterface) {
            return;
        }

        $this->cache->delete($this->cacheKey($userId, 'ids'));
        $this->cache->delete($this->cacheKey($userId, 'names'));
    }
}
