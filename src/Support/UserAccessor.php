<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Support;

use function array_key_exists;
use function get_object_vars;
use function is_array;
use function is_object;
use function method_exists;
use function ucfirst;

final class UserAccessor
{
    public static function get(mixed $user, string $key): mixed
    {
        if (is_array($user)) {
            return array_key_exists($key, $user) ? $user[$key] : null;
        }

        if (is_object($user)) {
            $vars = get_object_vars($user);
            if (array_key_exists($key, $vars)) {
                return $vars[$key];
            }

            $method = 'get' . ucfirst($key);
            if (method_exists($user, $method)) {
                return $user->{$method}();
            }
        }

        return null;
    }
}
