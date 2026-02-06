<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Tests\Support;

use PhpSoftBox\Orm\TypeCasting\Contracts\TypeHandlerInterface;

final class TestMarkerHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'test_marker';
    }

    public function castTo(mixed $value, array $options = []): int|float|string|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return 'mapped:' . (string) $value;
    }
}
