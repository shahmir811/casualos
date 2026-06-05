<?php

if (!function_exists('pdf_logo_data_uri')) {
    function pdf_logo_data_uri(int $maxWidth = 300): string
    {
        $path = public_path('images/casualite-logo.png');

        if (!file_exists($path) || !function_exists('imagecreatefrompng')) {
            return '';
        }

        $src = @imagecreatefrompng($path);
        if (!$src) {
            return '';
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        $scale = $origW > $maxWidth ? $maxWidth / $origW : 1.0;
        $newW  = (int) round($origW * $scale);
        $newH  = (int) round($origH * $scale);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        ob_start();
        imagepng($dst, null, 6);
        $bytes = ob_get_clean();

        return 'data:image/png;base64,' . base64_encode($bytes);
    }
}

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
