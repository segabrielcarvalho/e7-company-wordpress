<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Uploads;

use finfo;
use InvalidArgumentException;

final class ImageUploadProcessor
{
    /** @param callable(string): bool|null $isUploadedFile */
    public function __construct(
        private readonly string $storageRoot,
        private readonly string $publicRoot,
        private readonly int $maxBytes = 8_388_608,
        private readonly mixed $isUploadedFile = null
    ) {
        $storage = $this->normalizePath($storageRoot);
        $public = $this->normalizePath($publicRoot);
        if ($storage === $public || str_starts_with($storage . '/', $public . '/')) {
            throw new InvalidArgumentException('Lead uploads must be stored outside the public root.');
        }
    }

    /** @param array{name?:string,tmp_name?:string,size?:int,error?:int} $file */
    public function store(array $file, string $publicId): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $temporary = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($error !== UPLOAD_ERR_OK || $temporary === '' || $size < 1 || $size > $this->maxBytes) {
            throw new UploadException('Image upload failed validation.');
        }

        $checker = $this->isUploadedFile ?? static fn(string $path): bool => is_uploaded_file($path);
        if (!$checker($temporary)) {
            throw new UploadException('Upload source is not a valid HTTP upload.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            throw new UploadException('Only JPG, PNG and WebP images are accepted.');
        }

        $dimensions = @getimagesize($temporary);
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : 0;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : 0;
        if ($width < 1 || $height < 1 || $width > 12_000 || $height > 12_000 || ($width * $height) > 40_000_000) {
            throw new UploadException('Uploaded image dimensions are not accepted.');
        }

        $contents = file_get_contents($temporary);
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;
        if ($image === false || imagesx($image) < 1 || imagesy($image) < 1) {
            throw new UploadException('Uploaded image cannot be decoded.');
        }

        $safeId = preg_replace('/[^a-zA-Z0-9-]/', '', $publicId) ?: bin2hex(random_bytes(8));
        $directory = rtrim($this->storageRoot, '/\\') . '/' . $safeId;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            imagedestroy($image);
            throw new UploadException('Private upload directory could not be created.');
        }
        @chmod($directory, 0700);

        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination = $directory . '/' . $filename;
        $stored = $this->reencode($image, $mime, $destination);
        imagedestroy($image);
        if (!$stored) {
            @unlink($destination);
            throw new UploadException('Uploaded image could not be reprocessed.');
        }
        @chmod($destination, 0600);

        return $safeId . '/' . $filename;
    }

    /**
     * @param array<int, array{name?:string,tmp_name?:string,size?:int,error?:int}> $files
     * @return list<string>
     */
    public function storeMany(array $files, string $publicId): array
    {
        if (count($files) > 6) {
            throw new UploadException('A maximum of six vehicle photos is allowed.');
        }

        $stored = [];
        try {
            foreach ($files as $file) {
                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $stored[] = $this->store($file, $publicId);
            }
        } catch (\Throwable $error) {
            foreach ($stored as $relativePath) {
                $this->delete($relativePath);
            }
            throw $error;
        }

        return $stored;
    }

    public function delete(string $relativePath): void
    {
        $relativePath = ltrim(str_replace(['..', '\\'], '', $relativePath), '/');
        $path = rtrim($this->storageRoot, '/\\') . '/' . $relativePath;
        if (is_file($path)) {
            @unlink($path);
        }
        $directory = dirname($path);
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }

    public function absolutePath(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, '\\')) {
            return null;
        }
        $candidate = rtrim($this->storageRoot, '/\\') . '/' . ltrim($relativePath, '/');
        $real = realpath($candidate);
        $root = realpath($this->storageRoot);
        if ($real === false || $root === false || !str_starts_with($real . '/', rtrim($root, '/') . '/')) {
            return null;
        }
        return is_file($real) ? $real : null;
    }

    private function reencode(\GdImage $image, string $mime, string $destination): bool
    {
        if ($mime === 'image/jpeg') {
            return imagejpeg($image, $destination, 88);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        if ($mime === 'image/png') {
            return imagepng($image, $destination, 6);
        }
        return function_exists('imagewebp') && imagewebp($image, $destination, 88);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', rtrim($path, '/\\'));
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('Upload paths must be absolute.');
        }
        return $path;
    }
}
