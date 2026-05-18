<?php

if (!function_exists('lacs_format')) {
    /**
     * Format a number using the South Asian (Pakistani) lacs/crore grouping.
     *
     * Groups: ...XX,XX,XX,XXX  (last 3 digits, then pairs)
     * e.g. 25511990 → 2,55,11,990
     */
    function lacs_format(float|int|string $number, int $decimals = 0): string
    {
        $number   = (float) $number;
        $negative = $number < 0;
        $number   = abs($number);
        $rounded  = round($number, $decimals);
        $intPart  = (int) floor($rounded);
        $str      = (string) $intPart;

        if (strlen($str) <= 3) {
            $formatted = $str;
        } else {
            $last3     = substr($str, -3);
            $remaining = substr($str, 0, -3);
            $groups    = [];

            while ($remaining !== '') {
                $take      = min(2, strlen($remaining));
                $chunk     = substr($remaining, -$take);
                $remaining = substr($remaining, 0, -$take);
                $groups[]  = $chunk;
            }

            $formatted = implode(',', array_reverse($groups)) . ',' . $last3;
        }

        if ($decimals > 0) {
            $formatted .= substr(sprintf("%.{$decimals}f", $rounded), -($decimals + 1));
        }

        return ($negative ? '-' : '') . $formatted;
    }
}
