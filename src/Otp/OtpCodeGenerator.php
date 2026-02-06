<?php

declare(strict_types=1);

namespace PhpSoftBox\Auth\Otp;

use function random_int;
use function str_repeat;
use function strlen;

final class OtpCodeGenerator
{
    public function generate(int $length = 6): string
    {
        $length = $length > 0 ? $length : 6;
        $max    = (10 ** $length) - 1;
        $code   = (string) random_int(0, $max);

        if (strlen($code) < $length) {
            $code = str_repeat('0', $length - strlen($code)) . $code;
        }

        return $code;
    }
}
