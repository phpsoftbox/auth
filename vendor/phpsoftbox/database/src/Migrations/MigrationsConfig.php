<?php

declare(strict_types=1);

namespace PhpSoftBox\Database\Migrations;

use PhpSoftBox\Database\Exception\ConfigurationException;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function trim;

final class MigrationsConfig
{
    /** @var array<string, array{paths: list<string>}> */
    private array $connections = [];

    private string $defaultConnection = 'default';

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        if (!array_key_exists('migrations', $config) || !is_array($config['migrations'])) {
            throw new ConfigurationException('Migrations configuration not found.');
        }

        $connections = $config['connections'] ?? null;
        if (is_array($connections) && array_key_exists('default', $connections) && !is_string($connections['default'])) {
            throw new ConfigurationException('Connections "default" must be a connection name (string).');
        }

        $this->parse($config['migrations'], $connections);
    }

    public function defaultConnection(): string
    {
        return $this->defaultConnection;
    }

    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    /**
     * @return list<string>
     */
    public function paths(string $connection): array
    {
        if (!isset($this->connections[$connection])) {
            throw new ConfigurationException('Migrations are not configured for connection: ' . $connection);
        }

        return $this->connections[$connection]['paths'];
    }

    /**
     * @param array<string, mixed> $migrations
     * @param array<string, mixed>|null $connections
     */
    private function parse(array $migrations, mixed $connections): void
    {
        $default = $migrations['default'] ?? null;
        if (is_array($default)) {
            throw new ConfigurationException('Migrations "default" must be a connection name, not config array.');
        }

        if (is_string($default) && $default !== '') {
            $this->defaultConnection = $default;
            unset($migrations['default']);
        } elseif (is_array($connections)) {
            $defaultConnection = $connections['default'] ?? null;
            if (is_string($defaultConnection) && $defaultConnection !== '') {
                $this->defaultConnection = $defaultConnection;
            }
        }

        foreach ($migrations as $connection => $config) {
            if (!is_string($connection) || $connection === '') {
                continue;
            }

            if (!is_array($config)) {
                throw new ConfigurationException('Migration config for "' . $connection . '" must be array.');
            }

            $paths = $config['paths'] ?? null;
            if (is_string($paths)) {
                $paths = [$paths];
            }

            if (!is_array($paths) || $paths === []) {
                throw new ConfigurationException('Migration paths are required for connection: ' . $connection);
            }

            $paths = array_values(array_map(static function ($path): string {
                if (!is_string($path)) {
                    return '';
                }

                return trim($path);
            }, $paths));

            $paths = array_values(array_filter($paths, static fn (string $path): bool => $path !== ''));

            if ($paths === []) {
                throw new ConfigurationException('Migration paths are required for connection: ' . $connection);
            }

            $this->connections[$connection] = ['paths' => $paths];
        }

        if ($this->connections === []) {
            throw new ConfigurationException('Migrations configuration is empty.');
        }

        if (!isset($this->connections[$this->defaultConnection])) {
            throw new ConfigurationException('Default migration connection is not configured: ' . $this->defaultConnection);
        }
    }
}
