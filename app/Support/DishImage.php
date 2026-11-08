<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Makes every dish photo look the same: centre-cropped to 4:3 and resized to
 * 800 × 600 (WebP when the server supports it, otherwise JPEG).
 *
 * Needs PHP's GD extension (standard on cPanel). Without GD the original file
 * is stored untouched, and the CSS (`aspect-ratio: 4/3; object-fit: cover`)
 * still keeps the display consistent.
 */
class DishImage
{
    public const WIDTH = 800;

    public const HEIGHT = 600;

    /** Process an uploaded photo and return its path on the public disk. */
    public static function store(UploadedFile $file): string
    {
        $processed = static::process((string) file_get_contents($file->getRealPath()));

        if ($processed === null) {
            return $file->store('menu', 'public');
        }

        [$data, $extension] = $processed;
        $path = 'menu/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $data);

        return $path;
    }

    /**
     * Re-process a photo that is already stored.
     * Returns the new path, or null when it could not be converted.
     */
    public static function normalize(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $processed = static::process((string) $disk->get($path));

        if ($processed === null) {
            return null;
        }

        [$data, $extension] = $processed;
        $newPath = 'menu/'.Str::uuid().'.'.$extension;
        $disk->put($newPath, $data);
        $disk->delete($path);

        return $newPath;
    }

    /** @return array{0: string, 1: string}|null  [binary data, file extension] */
    protected static function process(string $binary): ?array
    {
        if ($binary === '' || ! extension_loaded('gd')) {
            return null;
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        $source = static::fixOrientation($source, $binary);

        $width = imagesx($source);
        $height = imagesy($source);
        $ratio = self::WIDTH / self::HEIGHT;

        if ($width / $height > $ratio) {
            $cropHeight = $height;
            $cropWidth = (int) round($height * $ratio);
        } else {
            $cropWidth = $width;
            $cropHeight = (int) round($width / $ratio);
        }

        $x = (int) floor(($width - $cropWidth) / 2);
        $y = (int) floor(($height - $cropHeight) / 2);

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($canvas, null, 82);
            $extension = 'webp';
        } else {
            imagejpeg($canvas, null, 85);
            $extension = 'jpg';
        }

        $data = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $data === '' ? null : [$data, $extension];
    }

    /** Phone photos are often rotated through an EXIF flag; undo that. */
    protected static function fixOrientation(\GdImage $image, string $binary): \GdImage
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagerotate')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }
}
