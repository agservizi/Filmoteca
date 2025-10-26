<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/url.php';

function images_supports_webp(): bool
{
    return function_exists('imagewebp') || class_exists('Imagick');
}

function images_detect_extension(string $mimeType): string
{
    return match ($mimeType) {
        'image/webp' => 'webp',
        'image/png' => 'png',
        'image/gif' => 'gif',
        default => 'jpg',
    };
}

/**
 * Generate poster variants for multiple responsive widths and return absolute file paths.
 *
 * @param string $binary         Raw uploaded image data.
 * @param string $targetBasePath Absolute path without extension for the target file.
 * @param array  $sizes          Map alias => max width (e.g. ['w154' => 154]).
 *
 * @return array<string,string>|null Variant alias => saved path map plus '_base' => main asset path.
 */
function images_save_variants(string $binary, string $targetBasePath, array $sizes): ?array
{
    if ($binary === '') {
        return null;
    }

    $useImagick = class_exists('Imagick');
    $useGd = function_exists('imagecreatefromstring');
    $results = [];

    $basePathWebp = $targetBasePath . '.webp';
    $basePathJpg = $targetBasePath . '.jpg';

    if ($useImagick) {
        $imagickClass = '\\Imagick';
        try {
            $imagick = new $imagickClass();
            $imagick->readImageBlob($binary);
            if (defined('Imagick::COLORSPACE_RGB')) {
                $imagick->setImageColorspace(constant('Imagick::COLORSPACE_RGB'));
            }
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(82);
            $imagick->writeImage($basePathWebp);
            $imagick->clear();
            $imagick->destroy();
            $results['_base'] = $basePathWebp;

            foreach ($sizes as $alias => $maxWidth) {
                $variant = new $imagickClass();
                $variant->readImageBlob($binary);
                if (defined('Imagick::COLORSPACE_RGB')) {
                    $variant->setImageColorspace(constant('Imagick::COLORSPACE_RGB'));
                }
                if (defined('Imagick::ALPHACHANNEL_OFF')) {
                    $variant->setImageAlphaChannel(constant('Imagick::ALPHACHANNEL_OFF'));
                }
                $variant->setImageFormat('webp');
                $filter = defined('Imagick::FILTER_LANCZOS') ? constant('Imagick::FILTER_LANCZOS') : 1;
                $variant->resizeImage($maxWidth, 0, $filter, 1, true);
                $variant->setImageCompressionQuality(82);
                $targetPath = $targetBasePath . '.' . $alias . '.webp';
                $variant->writeImage($targetPath);
                $variant->clear();
                $variant->destroy();
                $results[$alias] = $targetPath;
            }
        } catch (Throwable $e) {
            error_log('Imagick poster generation failed: ' . $e->getMessage());
            return null;
        }

        return $results;
    }

    if ($useGd) {
        $baseResource = imagecreatefromstring($binary);
        if (!$baseResource) {
            return null;
        }
        if (!imagewebp($baseResource, $basePathWebp, 82)) {
            imagedestroy($baseResource);
            return null;
        }
        imagedestroy($baseResource);
        $results['_base'] = $basePathWebp;

        foreach ($sizes as $alias => $maxWidth) {
            $resource = imagecreatefromstring($binary);
            if (!$resource) {
                return null;
            }
            $width = imagesx($resource);
            $height = imagesy($resource);
            $ratio = $width > 0 ? min(1, $maxWidth / $width) : 1;
            $targetWidth = (int) ($width * $ratio);
            $targetHeight = (int) ($height * $ratio);
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($resized, $resource, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            $targetPath = $targetBasePath . '.' . $alias . '.webp';
            if (!imagewebp($resized, $targetPath, 82)) {
                imagedestroy($resource);
                imagedestroy($resized);
                return null;
            }
            imagedestroy($resource);
            imagedestroy($resized);
            $results[$alias] = $targetPath;
        }

        return $results;
    }

    if (file_put_contents($basePathJpg, $binary) === false) {
        return null;
    }
    $results['_base'] = $basePathJpg;
    foreach ($sizes as $alias => $unused) {
        $targetPath = $targetBasePath . '.' . $alias . '.jpg';
        if (file_put_contents($targetPath, $binary) === false) {
            return null;
        }
        $results[$alias] = $targetPath;
    }

    return $results;
}

function images_generate_srcset(string $relativeBase, array $variants): array
{
    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2)), DIRECTORY_SEPARATOR);
    $docRootNormalized = str_replace('\\', '/', $docRoot);
    $srcset = [];
    foreach ($variants as $alias => $path) {
        $normalized = str_replace('\\', '/', $path);
        $relative = ltrim(str_replace($docRootNormalized, '', $normalized), '/');
        $srcset[] = [
            'size' => $alias,
            'url' => app_url($relative, true)
        ];
    }
    return $srcset;
}
