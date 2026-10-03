<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image upload pipeline ported from shopora
 * (ImageUploader + ImageHandler + ImageResizer), adapted to this project's
 * `public` storage disk so Storage::url() keeps working.
 *
 * - Safe unique naming (shopora: md5(microtime)); here Str::random.
 * - jpeg/png → webp (GD, quality 85) with optional center cover-crop;
 *   webp/gif/svg are kept as-is, exactly like shopora.
 * - Optional resized variants in `{folder}/{sizeKey}/` subfolders.
 * - Any failure → partial files removed, null returned (never fatal).
 * - No GD on the machine → original file is kept untouched, upload still
 *   succeeds (shopora would return null and break the upload entirely).
 */
class ImageUploadService
{
    public const WEBP_QUALITY = 85;

    public static function gdAvailable(): bool
    {
        return function_exists('imagewebp')
            && function_exists('imagecreatefromjpeg')
            && function_exists('imagecreatefrompng')
            && function_exists('imagecreatefromwebp');
    }

    /**
     * @param  array<string, array{0:int,1:int}>  $sizes  e.g. ['thumb' => [128, 128]]
     * @param  array{0:int,1:int}|null  $cover  center cover-crop for the main file, e.g. [512, 512]
     * @return string|null disk-relative path (e.g. avatars/xxx.webp) or null on failure
     */
    public static function upload(UploadedFile $file, string $folder, ?array $cover = null, array $sizes = [], int $quality = self::WEBP_QUALITY): ?string
    {
        $disk = Storage::disk('public');
        $folder = trim($folder, '/');

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $name = Str::random(40).'.'.$ext;
        $path = $folder.'/'.$name;

        try {
            if (! $disk->exists($folder)) {
                $disk->makeDirectory($folder);
            }
            // NOTE: $file->move() instead of putFileAs(): the latter opens
            // the upload via getRealPath(), which returns false for temp
            // uploads on some Windows stacks (Apache/mod_fcgid here) and
            // makes every upload fail with "Path cannot be empty".
            // move() uses the tmp pathname directly and never needs realpath.
            $file->move($disk->path($folder), $name);
            if (! $disk->exists($path)) {
                return null;
            }

            // Reject non-image content even if the mime check passed.
            if (! @getimagesize($disk->path($path))) {
                $disk->delete($path);

                return null;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && self::gdAvailable()) {
                $webpName = pathinfo($name, PATHINFO_FILENAME).'.webp';
                $webpPath = $folder.'/'.$webpName;
                if (self::convertToWebp($disk->path($path), $disk->path($webpPath), $cover, $quality)) {
                    $disk->delete($path);
                    $path = $webpPath;
                    $name = $webpName;
                }
                // Conversion failed → keep the original upload, like a plain store().
            }

            if ($sizes && str_ends_with(strtolower($name), '.webp') && self::gdAvailable()) {
                self::resizeVariants($disk->path($path), $folder, $name, $sizes, $quality);
            }

            return $path;
        } catch (\Throwable $e) {
            \Log::warning('avatar-upload-failed', ['class' => get_class($e), 'msg' => $e->getMessage()]);
            $disk->delete($path);

            return null;
        }
    }

    /**
     * Store a generic (non-image) upload on the public disk, ported from
     * shopora's Uploader::upload (safe naming). Uses move() so it never
     * depends on getRealPath() — see upload().
     *
     * @return string|null disk-relative path or null on failure
     */
    public static function storeFile(UploadedFile $file, string $folder): ?string
    {
        $disk = Storage::disk('public');
        $folder = trim($folder, '/');

        $original = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'file';
        $name = time().'_'.$safe;
        $path = $folder.'/'.$name;

        try {
            if (! $disk->exists($folder)) {
                $disk->makeDirectory($folder);
            }
            $file->move($disk->path($folder), $name);

            return $disk->exists($path) ? $path : null;
        } catch (\Throwable $e) {
            \Log::warning('file-upload-failed', ['class' => get_class($e), 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Delete the main file plus its resized variants (shopora ImageHandler::delete).
     */
    public static function delete(?string $path, array $sizes = []): bool
    {
        if (empty($path)) {
            return true;
        }
        $disk = Storage::disk('public');
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        try {
            $disk->delete($path);
            if ($sizes) {
                $dir = pathinfo($path, PATHINFO_DIRNAME);
                $base = pathinfo($path, PATHINFO_BASENAME);
                foreach (array_keys($sizes) as $sizeKey) {
                    $disk->delete(($dir !== '.' ? $dir.'/' : '').$sizeKey.'/'.$base);
                }
            }
        } catch (\Throwable) {
            // Best effort — a missing file must never break the request.
        }

        return true;
    }

    /**
     * jpeg/png → webp (shopora ImageUploader::convertToWebP) with optional
     * center cover-crop. Returns false instead of throwing.
     */
    public static function convertToWebp(string $inputPath, string $outputPath, ?array $cover = null, int $quality = self::WEBP_QUALITY): bool
    {
        try {
            $info = @getimagesize($inputPath);
            if (! $info) {
                return false;
            }

            $image = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($inputPath),
                IMAGETYPE_PNG => @imagecreatefrompng($inputPath),
                default => false,
            };
            if (! $image) {
                return false;
            }

            if (! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            // Preserve transparency while converting.
            imagealphablending($image, false);
            imagesavealpha($image, true);

            if ($cover !== null) {
                $cropped = self::coverCrop($image, (int) $cover[0], (int) $cover[1]);
                if ($cropped !== null) {
                    imagedestroy($image);
                    $image = $cropped;
                }
            }

            $ok = @imagewebp($image, $outputPath, $quality);
            imagedestroy($image);

            return (bool) $ok && is_file($outputPath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cover-crop variants into `{folder}/{sizeKey}/` (shopora ImageResizer::resize).
     * Unreadable sources are skipped so one bad size never kills the upload.
     */
    public static function resizeVariants(string $absolutePath, string $folder, string $fileName, array $sizes, int $quality = self::WEBP_QUALITY): void
    {
        $disk = Storage::disk('public');
        $folder = trim($folder, '/');

        $src = @imagecreatefromwebp($absolutePath);
        if (! $src) {
            return;
        }
        try {
            [$width, $height] = @getimagesize($absolutePath) ?: [0, 0];
            if ($width <= 0 || $height <= 0) {
                return;
            }

            foreach ($sizes as $sizeKey => $dimensions) {
                try {
                    $sizeDir = $folder.'/'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $sizeKey);
                    if (! $disk->exists($sizeDir)) {
                        $disk->makeDirectory($sizeDir);
                    }
                    $cropped = self::coverCrop($src, (int) $dimensions[0], (int) $dimensions[1]);
                    if ($cropped === null) {
                        continue;
                    }
                    @imagewebp($cropped, $disk->path($sizeDir.'/'.$fileName), $quality);
                    imagedestroy($cropped);
                } catch (\Throwable) {
                    continue;
                }
            }
        } finally {
            imagedestroy($src);
        }
    }

    /**
     * Center cover-crop a GD image resource to exact dimensions.
     *
     * @param  resource|\GdImage  $src
     * @return resource|\GdImage|null
     */
    private static function coverCrop($src, int $targetWidth, int $targetHeight)
    {
        if ($targetWidth <= 0 || $targetHeight <= 0) {
            return null;
        }
        $width = imagesx($src);
        $height = imagesy($src);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $srcRatio = $width / $height;
        $targetRatio = $targetWidth / $targetHeight;

        if ($srcRatio > $targetRatio) {
            $cropWidth = (int) round($height * $targetRatio);
            $cropHeight = $height;
            $cropX = (int) round(($width - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $width;
            $cropHeight = (int) round($width / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($height - $cropHeight) / 2);
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $resized) {
            return null;
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        if (! imagecopyresampled($resized, $src, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropWidth, $cropHeight)) {
            imagedestroy($resized);

            return null;
        }

        return $resized;
    }
}
