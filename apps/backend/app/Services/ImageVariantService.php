<?php

namespace App\Services;

use App\Models\EventPhoto;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageVariantService
{
    public function generate(EventPhoto $photo): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD extension is not available for image variant generation.');
        }

        $disk = Storage::disk($photo->storage_disk);

        if (! $disk->exists($photo->storage_path)) {
            throw new RuntimeException('Photo file does not exist for variant generation.');
        }

        $sourcePath = $disk->path($photo->storage_path);
        $sourceContents = $disk->get($photo->storage_path);
        $image = @imagecreatefromstring((string) $sourceContents);

        if (! $image instanceof \GdImage) {
            throw new RuntimeException('Unable to decode image for variant generation.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $mime = $photo->mime_type ?: $this->detectMimeType($sourcePath);

        $medium = config('services.image_variants.medium', ['width' => 1280, 'height' => 1280]);
        $thumbnail = config('services.image_variants.thumbnail', ['width' => 256, 'height' => 256]);
        $socialOg = config('services.image_variants.social_og', ['width' => 1200, 'height' => 630]);

        $variants = [];

        try {
            $mediumImage = $this->resizeFit($image, $width, $height, (int) $medium['width'], (int) $medium['height']);
            $variants['medium'] = $this->storeVariant($photo, 'medium', $mediumImage, (string) $mime);

            $thumbnailImage = $this->cropSquare($image, $width, $height, (int) $thumbnail['width'], (int) $thumbnail['height']);
            $variants['thumbnail'] = $this->storeVariant($photo, 'thumbnail', $thumbnailImage, (string) $mime);

            $socialImage = $this->fitTo($image, $width, $height, (int) $socialOg['width'], (int) $socialOg['height']);
            $variants['social_og'] = $this->storeVariant($photo, 'social_og', $socialImage, (string) $mime);
        } finally {
            imagedestroy($image);
        }

        return $variants;
    }

    private function resizeFit(\GdImage $image, int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): \GdImage
    {
        $ratio = min($maxWidth / max($sourceWidth, 1), $maxHeight / max($sourceHeight, 1));
        $targetWidth = (int) max(1, floor($sourceWidth * $ratio));
        $targetHeight = (int) max(1, floor($sourceHeight * $ratio));

        $result = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($result, false);
        imagesavealpha($result, true);
        imagecopyresampled($result, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        return $result;
    }

    private function cropSquare(\GdImage $image, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight): \GdImage
    {
        $side = min($sourceWidth, $sourceHeight);
        $srcX = (int) max(0, floor(($sourceWidth - $side) / 2));
        $srcY = (int) max(0, floor(($sourceHeight - $side) / 2));

        $result = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($result, false);
        imagesavealpha($result, true);
        imagecopyresampled($result, $image, 0, 0, $srcX, $srcY, $targetWidth, $targetHeight, $side, $side);

        return $result;
    }

    private function fitTo(\GdImage $image, int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight): \GdImage
    {
        $ratio = max($targetWidth / max($sourceWidth, 1), $targetHeight / max($sourceHeight, 1));
        $scaledWidth = (int) max(1, floor($sourceWidth * $ratio));
        $scaledHeight = (int) max(1, floor($sourceHeight * $ratio));

        $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $sourceWidth, $sourceHeight);

        $srcX = (int) max(0, floor(($scaledWidth - $targetWidth) / 2));
        $srcY = (int) max(0, floor(($scaledHeight - $targetHeight) / 2));

        $result = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($result, false);
        imagesavealpha($result, true);
        imagecopyresampled(
            $result,
            $scaled,
            0,
            0,
            $srcX,
            $srcY,
            $targetWidth,
            $targetHeight,
            $targetWidth,
            $targetHeight,
        );

        imagedestroy($scaled);

        return $result;
    }

    private function storeVariant(EventPhoto $photo, string $name, \GdImage $image, string $mime): array
    {
        $directory = sprintf('event-photos/%d/variants', $photo->id);
        $extension = $this->extensionFromMime($mime);
        $variantPath = sprintf('%s/%s.%s', $directory, $name, $extension);

        $width = imagesx($image);
        $height = imagesy($image);

        $tmpStream = fopen('php://temp', 'r+');

        try {
            if (str_contains($mime, 'png')) {
                imagepng($image, $tmpStream);
            } elseif (str_contains($mime, 'webp') && function_exists('imagewebp')) {
                imagewebp($image, $tmpStream, 82);
            } else {
                imagejpeg($image, $tmpStream, 82);
                $extension = 'jpg';
                $variantPath = sprintf('%s/%s.%s', $directory, $name, $extension);
            }

            rewind($tmpStream);
            $contents = stream_get_contents($tmpStream);
        } finally {
            if (is_resource($tmpStream)) {
                fclose($tmpStream);
            }

        }

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Failed to encode image variant.');
        }

        Storage::disk($photo->storage_disk)->put($variantPath, $contents);

        return [
            'path' => $variantPath,
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'bytes' => strlen($contents),
        ];
    }

    private function detectMimeType(string $sourcePath): string
    {
        $guessed = @mime_content_type($sourcePath);

        return is_string($guessed) && $guessed !== '' ? $guessed : 'image/jpeg';
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
