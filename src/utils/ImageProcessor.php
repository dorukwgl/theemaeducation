<?php

namespace EMA\Utils;

use EMA\Utils\Logger;

class ImageProcessor
{
    public static function processImage(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth = 1200,
        int $maxHeight = 1200,
        int $quality = 90
    ): bool {
        return self::processImageWithScaling($sourcePath, $destinationPath, $maxWidth, $maxHeight, $quality);
    }

    public static function compressImage(
        string $sourcePath,
        string $destinationPath,
        int $quality = 100
    ): bool {
        try {
            $imageType = self::getImageType($sourcePath);

            if (!$imageType) {
                Logger::error('Unsupported image type for compression', [
                    'source' => $sourcePath
                ]);
                return false;
            }

            $sourceImage = self::loadImage($sourcePath, $imageType);

            if (!$sourceImage) {
                Logger::error('Failed to load image for compression', [
                    'source' => $sourcePath,
                    'type' => $imageType
                ]);
                return false;
            }

            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);

            $destinationImage = imagecreatetruecolor($originalWidth, $originalHeight);

            if (!$destinationImage) {
                imagedestroy($sourceImage);
                Logger::error('Failed to create destination image for compression', [
                    'width' => $originalWidth,
                    'height' => $originalHeight
                ]);
                return false;
            }

            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);

            imagecopy(
                $destinationImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $originalWidth,
                $originalHeight
            );

            $result = self::saveImage($destinationImage, $destinationPath, $imageType, $quality);

            imagedestroy($sourceImage);
            imagedestroy($destinationImage);

            if (!$result) {
                Logger::error('Failed to save compressed image', [
                    'destination' => $destinationPath,
                    'type' => $imageType
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('Image compression error', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private static function processImageWithScaling(
        string $sourcePath,
        string $destinationPath,
        int $maxWidth = 1200,
        int $maxHeight = 1200,
        int $quality = 90
    ): bool {
        try {
            $imageType = self::getImageType($sourcePath);

            if (!$imageType) {
                Logger::error('Unsupported image type', [
                    'source' => $sourcePath
                ]);
                return false;
            }

            $sourceImage = self::loadImage($sourcePath, $imageType);

            if (!$sourceImage) {
                Logger::error('Failed to load image', [
                    'source' => $sourcePath,
                    'type' => $imageType
                ]);
                return false;
            }

            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);

            $dimensions = self::calculateDimensions($originalWidth, $originalHeight, $maxWidth, $maxHeight);

            $destinationImage = imagecreatetruecolor($dimensions['width'], $dimensions['height']);

            if (!$destinationImage) {
                imagedestroy($sourceImage);
                Logger::error('Failed to create destination image', [
                    'dimensions' => $dimensions
                ]);
                return false;
            }

            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);

            imagecopyresampled(
                $destinationImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $dimensions['width'],
                $dimensions['height'],
                $originalWidth,
                $originalHeight
            );

            $result = self::saveImage($destinationImage, $destinationPath, $imageType, $quality);

            imagedestroy($sourceImage);
            imagedestroy($destinationImage);

            if (!$result) {
                Logger::error('Failed to save processed image', [
                    'destination' => $destinationPath,
                    'type' => $imageType
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('Image processing error', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function calculateDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight
    ): array {
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return [
                'width' => $originalWidth,
                'height' => $originalHeight
            ];
        }

        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);

        return [
            'width' => (int) round($originalWidth * $ratio),
            'height' => (int) round($originalHeight * $ratio)
        ];
    }

    public static function getImageType(string $filePath): ?string
    {
        $imageInfo = getimagesize($filePath);

        if (!$imageInfo) {
            return null;
        }

        $imageType = image_type_to_extension($imageInfo[2], false);

        return in_array($imageType, ['jpeg', 'png', 'gif', 'webp']) ? $imageType : null;
    }

    private static function loadImage(string $filePath, string $type)
    {
        switch ($type) {
            case 'jpeg':
                return imagecreatefromjpeg($filePath);
            case 'png':
                return imagecreatefrompng($filePath);
            case 'gif':
                return imagecreatefromgif($filePath);
            case 'webp':
                return imagecreatefromwebp($filePath);
            default:
                return null;
        }
    }

    private static function saveImage($image, string $filePath, string $type, int $quality): bool
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        switch ($type) {
            case 'jpeg':
                $result = imagejpeg($image, $filePath, $quality);
                break;
            case 'png':
                $pngQuality = (int) round(($quality / 100) * 9);
                $result = imagepng($image, $filePath, $pngQuality);
                break;
            case 'gif':
                $result = imagegif($image, $filePath);
                break;
            case 'webp':
                $result = imagewebp($image, $filePath, $quality);
                break;
            default:
                return false;
        }

        if ($result) {
            chmod($filePath, 0644);
        }

        return $result;
    }

    public static function validateProfileImage(array $file): array
    {
        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['profile_image'] = self::getUploadErrorMessage($file['error']);
            return $errors;
        }

        $fileSize = $file['size'];
        $maxSize = 5 * 1024 * 1024;

        if ($fileSize > $maxSize) {
            $errors['profile_image'] = 'Profile image must be less than 5MB';
            return $errors;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);

        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!in_array($mimeType, $allowedTypes)) {
            $errors['profile_image'] = 'Profile image must be a valid image (JPEG, PNG, GIF, or WebP)';
            return $errors;
        }

        if (!@getimagesize($file['tmp_name'])) {
            $errors['profile_image'] = 'Profile image file is corrupted or invalid';
            return $errors;
        }

        return $errors;
    }

    private static function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'Profile image exceeds the maximum file size';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Profile image exceeds the maximum file size';
            case UPLOAD_ERR_PARTIAL:
                return 'Profile image was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No profile image was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary directory';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write profile image to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the profile image upload';
            default:
                return 'Unknown upload error';
        }
    }
}
