<?php

namespace App\Domains\Demo\Support;

/** A branded 1600×1000 JPEG (deep green gradient, category and title) used until real photos are added. */
final class SampleImage
{
    public static function make(string $category, string $title): string
    {
        $w = 1600;
        $h = 1000;
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = imagecolorallocate($img, self::channel(29 - 15 * $t), self::channel(74 - 45 * $t), self::channel(60 - 35 * $t));
            imageline($img, 0, $y, $w, $y, $c === false ? 0 : $c);
        }
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        $soft = (int) imagecolorallocate($img, 207, 227, 218);
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        if (function_exists('imagettftext') && is_file($font)) {
            imagettftext($img, 30, 0, 90, 150, $soft, $font, strtoupper($category).' · SAMPLE');
            imagettftext($img, 54, 0, 90, 260, $white, $font, wordwrap($title, 26, "\n"));
            imagettftext($img, 26, 0, 90, $h - 90, $soft, $font, 'Thabekhulu auctions');
        } else {
            imagestring($img, 5, 90, 150, strtoupper($category).' - SAMPLE', $soft);
            imagestring($img, 5, 90, 200, $title, $white);
        }
        $path = tempnam(sys_get_temp_dir(), 'sample').'.jpg';
        imagejpeg($img, $path, 88);
        imagedestroy($img);

        return $path;
    }

    /** @return int<0, 255> */
    private static function channel(float $v): int
    {
        return max(0, min(255, (int) round($v)));
    }
}
