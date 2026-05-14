<?php

declare(strict_types=1);

namespace Juling\DevTools\Support;

class StrHelper
{
    public static function ltrim($value, $chars): string
    {
        $str = mb_substr($value, 0, 1);

        if ($str === $chars) {
            $value = mb_substr($value, 1);
        }

        return $value;
    }

    public static function rtrim($value, $chars): string
    {
        $str = mb_substr($value, -1);

        if ($str === $chars) {
            $value = mb_substr($value, 0, -1, 'UTF-8');
        }

        return $value;
    }
}
