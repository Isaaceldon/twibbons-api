<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ImageProcessingService
{
    /**
     * Combine user photo with campaign frame.
     *
     * @param string $photoPath
     * @param string $framePath
     * @param array $customizations
     * @return string Path to result image
     */
    public function combinePhotoWithFrame(
        string $photoPath,
        string $framePath,
        array $customizations = []
    ): string {
        // Load user photo
        $photo = Image::make($photoPath);

        // Apply photo customizations
        $photo = $this->applyPhotoCustomizations($photo, $customizations);

        // Create canvas
        $canvas = Image::canvas(1080, 1080);

        // Place photo on canvas
        $canvas->insert($photo, 'center');

        // Load and apply frame overlay
        $frame = Image::make($framePath);

        if ($frame->width() != 1080 || $frame->height() != 1080) {
            $frame->resize(1080, 1080);
        }

        $canvas->insert($frame, 'top-left', 0, 0);

        // Apply additional customizations
        $canvas = $this->applyTextAndStickers($canvas, $customizations);

        // Save result
        $outputPath = sys_get_temp_dir() . '/' . uniqid('twibbon_') . '.png';
        $canvas->save($outputPath, 95, 'png');

        return $outputPath;
    }

    /**
     * Apply customizations to photo.
     */
    protected function applyPhotoCustomizations($photo, array $customizations)
    {
        // Scale
        if (isset($customizations['photo_scale'])) {
            $scale = $customizations['photo_scale'];
            $newWidth = $photo->width() * $scale;
            $newHeight = $photo->height() * $scale;
            $photo->resize($newWidth, $newHeight);
        }

        // Rotation
        if (isset($customizations['photo_rotation'])) {
            $photo->rotate(-$customizations['photo_rotation']);
        }

        // Fit to square
        $photo->fit(1080, 1080);

        // Apply filters
        if (isset($customizations['photo_filter'])) {
            $photo = $this->applyFilter($photo, $customizations['photo_filter']);
        }

        // Brightness
        if (isset($customizations['brightness'])) {
            $photo->brightness($customizations['brightness']);
        }

        // Contrast
        if (isset($customizations['contrast'])) {
            $photo->contrast($customizations['contrast']);
        }

        // Blur
        if (isset($customizations['blur'])) {
            $photo->blur($customizations['blur']);
        }

        return $photo;
    }

    /**
     * Apply filter to image.
     */
    protected function applyFilter($image, string $filter)
    {
        return match($filter) {
            'grayscale' => $image->greyscale(),
            'sepia' => $image->greyscale()->colorize(30, 15, 0),
            'vintage' => $image->contrast(5)->colorize(25, 15, -10),
            'warm' => $image->colorize(20, 10, -10),
            'cool' => $image->colorize(-10, -5, 15),
            'invert' => $image->invert(),
            default => $image,
        };
    }

    /**
     * Apply text and stickers to canvas.
     */
    protected function applyTextAndStickers($canvas, array $customizations)
    {
        // Add text
        if (isset($customizations['text']) && !empty($customizations['text'])) {
            $canvas = $this->addText($canvas, $customizations);
        }

        // Add stickers
        if (isset($customizations['stickers']) && is_array($customizations['stickers'])) {
            $canvas = $this->addStickers($canvas, $customizations['stickers']);
        }

        return $canvas;
    }

    /**
     * Add text to image.
     */
    protected function addText($canvas, array $customizations)
    {
        $text = $customizations['text'];
        $textColor = $customizations['text_color'] ?? '#FFFFFF';
        $textSize = $customizations['text_size'] ?? 36;
        $textPosition = $customizations['text_position'] ?? ['x' => 540, 'y' => 950];
        $textAlign = $customizations['text_align'] ?? 'center';
        $textFont = $customizations['text_font'] ?? 'Roboto-Bold.ttf';

        try {
            $canvas->text($text, $textPosition['x'], $textPosition['y'], function($font) use ($textSize, $textColor, $textAlign, $textFont) {
                $fontPath = $this->getFontPath($textFont);

                if (file_exists($fontPath)) {
                    $font->file($fontPath);
                }

                $font->size($textSize);
                $font->color($textColor);
                $font->align($textAlign);
                $font->valign('middle');

                // Add shadow for better visibility
                $font->shadow(2, 2, 2, 'rgba(0, 0, 0, 0.5)');
            });
        } catch (\Exception $e) {
            \Log::warning('Failed to add text to image', [
                'error' => $e->getMessage(),
            ]);
        }

        return $canvas;
    }

    /**
     * Add stickers to image.
     */
    protected function addStickers($canvas, array $stickers)
    {
        foreach ($stickers as $sticker) {
            try {
                $stickerPath = $this->downloadSticker($sticker['url']);

                if ($stickerPath) {
                    $stickerImage = Image::make($stickerPath);

                    // Resize
                    if (isset($sticker['size'])) {
                        $stickerImage->resize($sticker['size'], $sticker['size'], function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    }

                    // Rotate
                    if (isset($sticker['rotation'])) {
                        $stickerImage->rotate(-$sticker['rotation']);
                    }

                    // Apply opacity
                    if (isset($sticker['opacity'])) {
                        $stickerImage->opacity($sticker['opacity'] * 100);
                    }

                    // Insert
                    $x = $sticker['x'] ?? 0;
                    $y = $sticker['y'] ?? 0;
                    $canvas->insert($stickerImage, 'top-left', $x, $y);

                    unlink($stickerPath);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to add sticker', [
                    'error' => $e->getMessage(),
                    'sticker' => $sticker,
                ]);
            }
        }

        return $canvas;
    }

    /**
     * Optimize image for web.
     */
    public function optimizeForWeb(string $imagePath, int $maxWidth = 1080): string
    {
        $image = Image::make($imagePath);

        // Resize if too large
        if ($image->width() > $maxWidth) {
            $image->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Optimize quality
        $outputPath = sys_get_temp_dir() . '/' . uniqid('optimized_') . '.png';
        $image->save($outputPath, 85, 'png');

        return $outputPath;
    }

    /**
     * Create thumbnail.
     */
    public function createThumbnail(string $imagePath, int $size = 300): string
    {
        $image = Image::make($imagePath);
        $image->fit($size, $size);

        $outputPath = sys_get_temp_dir() . '/' . uniqid('thumb_') . '.png';
        $image->save($outputPath, 80, 'png');

        return $outputPath;
    }

    /**
     * Extract colors from image.
     */
    public function extractColors(string $imagePath, int $count = 5): array
    {
        try {
            $palette = \ColorThief\ColorThief::getPalette($imagePath, $count, 10);

            return array_map(function($rgb) {
                return sprintf("#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2]);
            }, $palette);
        } catch (\Exception $e) {
            \Log::warning('Color extraction failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Make image circular.
     */
    public function makeCircular(string $imagePath): string
    {
        $image = Image::make($imagePath);

        // Ensure square
        $size = min($image->width(), $image->height());
        $image->fit($size, $size);

        // Create circular mask
        $mask = Image::canvas($size, $size, null);
        $mask->circle($size, $size / 2, $size / 2, function ($draw) {
            $draw->background('#ffffff');
        });

        // Apply mask
        $image->mask($mask, false);

        $outputPath = sys_get_temp_dir() . '/' . uniqid('circular_') . '.png';
        $image->save($outputPath, 95, 'png');

        return $outputPath;
    }

    /**
     * Add watermark to image.
     */
    public function addWatermark(string $imagePath, string $watermarkText = 'Twibbons'): string
    {
        $image = Image::make($imagePath);

        $image->text($watermarkText, $image->width() - 10, $image->height() - 10, function($font) {
            $font->file($this->getFontPath('Roboto-Regular.ttf'));
            $font->size(12);
            $font->color('#ffffff');
            $font->align('right');
            $font->valign('bottom');
            $font->shadow(1, 1, 1, 'rgba(0, 0, 0, 0.3)');
        });

        $outputPath = sys_get_temp_dir() . '/' . uniqid('watermarked_') . '.png';
        $image->save($outputPath, 90, 'png');

        return $outputPath;
    }

    /**
     * Convert image to grayscale.
     */
    public function convertToGrayscale(string $imagePath): string
    {
        $image = Image::make($imagePath);
        $image->greyscale();

        $outputPath = sys_get_temp_dir() . '/' . uniqid('grayscale_') . '.png';
        $image->save($outputPath, 90, 'png');

        return $outputPath;
    }

    /**
     * Crop image to specific dimensions.
     */
    public function crop(string $imagePath, int $width, int $height, int $x = 0, int $y = 0): string
    {
        $image = Image::make($imagePath);
        $image->crop($width, $height, $x, $y);

        $outputPath = sys_get_temp_dir() . '/' . uniqid('cropped_') . '.png';
        $image->save($outputPath, 90, 'png');

        return $outputPath;
    }

    /**
     * Download sticker from URL or S3.
     */
    protected function downloadSticker(string $url): ?string
    {
        try {
            if (strpos($url, 's3://') === 0 || strpos($url, 'stickers/') !== false) {
                $content = Storage::disk('s3')->get($url);
            } else {
                $content = file_get_contents($url);
            }

            if ($content === false) {
                return null;
            }

            $tempPath = sys_get_temp_dir() . '/' . uniqid('sticker_') . '.png';
            file_put_contents($tempPath, $content);

            return $tempPath;
        } catch (\Exception $e) {
            \Log::warning('Failed to download sticker', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get font file path.
     */
    protected function getFontPath(string $fontName): string
    {
        $paths = [
            storage_path('fonts/' . $fontName),
            public_path('fonts/' . $fontName),
            base_path('resources/fonts/' . $fontName),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // System font fallback
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    }

    /**
     * Upload processed image to S3.
     */
    public function uploadToS3(string $localPath, string $s3Path): string
    {
        $content = file_get_contents($localPath);
        Storage::disk('s3')->put($s3Path, $content, 'public');

        return Storage::disk('s3')->url($s3Path);
    }

    /**
     * Get image dimensions.
     */
    public function getDimensions(string $imagePath): array
    {
        $image = Image::make($imagePath);

        return [
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }

    /**
     * Check if image has transparency.
     */
    public function hasTransparency(string $imagePath): bool
    {
        $image = Image::make($imagePath);

        // Check if image has alpha channel
        return $image->getCore()->getImageAlphaChannel() !== \Imagick::ALPHACHANNEL_UNDEFINED;
    }

    /**
     * Ensure image has transparent background.
     */
    public function ensureTransparent(string $imagePath): string
    {
        $image = Image::make($imagePath);

        // If not PNG, convert to PNG with transparency
        $outputPath = sys_get_temp_dir() . '/' . uniqid('transparent_') . '.png';
        $image->save($outputPath, 95, 'png');

        return $outputPath;
    }
}
