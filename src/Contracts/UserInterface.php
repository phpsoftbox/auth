<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Contracts;

interface UserInterface
{
    public function id(): int|string|null;

    public function get(string $key, mixed $default = null): mixed;

    /**
     * @param class-string<UserInterface> $className Параметр, для того чтобы указать в override, что метод
     *                                               возвращает экземпляр определенного класса. Это может быть
     *                                               полезно для статического анализа кода и автодополнения в IDE.
     *
     * ```php
     * override(UserInterface::identity(0), map([
     * '' => '@'
     * ]));
     * ```
     */
    public function identity(?string $className = null): mixed;
}
