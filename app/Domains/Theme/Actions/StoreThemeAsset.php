<?php

namespace App\Domains\Theme\Actions;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores logos and favicons for a theme. SVGs are sanitised (scripts,
 * event handlers and external references removed); PNG and WebP favicons
 * are resized to 32 px and a 180 px Apple touch icon.
 */
class StoreThemeAsset
{
    /**
     * @return array<string, string> theme value keys => stored paths
     */
    public function handle(string $scope, string $kind, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $base = "theme/{$scope}/{$kind}-".Str::lower(Str::random(12));

        if ($extension === 'svg') {
            $clean = $this->sanitiseSvg((string) file_get_contents($file->getRealPath()));
            Storage::disk('public')->put("{$base}.svg", $clean);

            return [$kind => "{$base}.svg"];
        }

        if ($kind !== 'favicon') {
            $path = $file->storeAs(dirname($base), basename($base).".{$extension}", 'public');

            return [$kind => (string) $path];
        }

        return [
            'favicon' => $this->resized($file, "{$base}-32.png", 32),
            'favicon_apple' => $this->resized($file, "{$base}-180.png", 180),
        ];
    }

    private function sanitiseSvg(string $svg): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($svg);

        if ($clean === false || trim($clean) === '') {
            throw ValidationException::withMessages(['file' => __('This SVG couldn’t be read. Export it again as a plain SVG.')]);
        }

        return $clean;
    }

    /**
     * @param  int<1, max>  $size
     */
    private function resized(UploadedFile $file, string $path, int $size): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['file' => __('This image couldn’t be read.')]);
        }

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        // Fit inside the square, keeping proportions.
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min($size / $w, $size / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        imagecopyresampled($canvas, $source, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($canvas);
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }
}
