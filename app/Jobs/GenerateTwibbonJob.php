<?php

namespace App\Jobs;

use App\Models\UserGeneratedImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class GenerateTwibbonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 60;
    public $backoff = [10, 30];

    protected UserGeneratedImage $userImage;

    /**
     * Create a new job instance.
     */
    public function __construct(UserGeneratedImage $userImage)
    {
        $this->userImage = $userImage;
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get user photo from S3
            $photoContent = Storage::disk('s3')->get($this->userImage->photo_path);
            $photoPath = sys_get_temp_dir() . '/' . uniqid('photo_') . '.jpg';
            file_put_contents($photoPath, $photoContent);

            // Get campaign template (frame) from S3
            $template = $this->userImage->campaignTemplate;
            $frameContent = Storage::disk('s3')->get($template->file_path);
            $framePath = sys_get_temp_dir() . '/' . uniqid('frame_') . '.png';
            file_put_contents($framePath, $frameContent);

            // Process and combine images
            $resultPath = $this->combinePhotoWithFrame(
                $photoPath,
                $framePath,
                $this->userImage->customizations
            );

            // Upload result to S3
            $s3ResultPath = 'user-twibbons/' . $this->userImage->id . '.png';
            Storage::disk('s3')->put(
                $s3ResultPath,
                file_get_contents($resultPath)
            );

            // Update record with result path
            $this->userImage->update([
                'result_path' => $s3ResultPath,
            ]);

            // Clean up temp files
            $this->cleanupTempFiles([$photoPath, $framePath, $resultPath]);

            \Log::info("Twibbon generated successfully", [
                'user_image_id' => $this->userImage->id,
                'campaign_id' => $this->userImage->campaign_id,
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Combine user photo with campaign frame.
     */
    protected function combinePhotoWithFrame(
        string $photoPath,
        string $framePath,
        array $customizations
    ): string {
        $outputPath = sys_get_temp_dir() . '/' . uniqid('twibbon_') . '.png';

        try {
            // Load user photo
            $photo = Image::make($photoPath);

            // Apply photo customizations
            $photo = $this->applyPhotoCustomizations($photo, $customizations);

            // Create canvas (1080x1080)
            $canvas = Image::canvas(1080, 1080);

            // Place photo on canvas
            $canvas->insert($photo, 'center');

            // Load and apply frame overlay
            $frame = Image::make($framePath);

            // Resize frame if needed
            if ($frame->width() != 1080 || $frame->height() != 1080) {
                $frame->resize(1080, 1080);
            }

            // Overlay frame on top of photo
            $canvas->insert($frame, 'top-left', 0, 0);

            // Apply additional customizations (text, stickers)
            $canvas = $this->applyAdditionalCustomizations($canvas, $customizations);

            // Save final result
            $canvas->save($outputPath, 95, 'png');

            return $outputPath;

        } catch (\Exception $e) {
            \Log::error("Error combining photo with frame", [
                'error' => $e->getMessage(),
                'user_image_id' => $this->userImage->id,
            ]);
            throw $e;
        }
    }

    /**
     * Apply customizations to user photo.
     */
    protected function applyPhotoCustomizations($photo, array $customizations)
    {
        // Apply scale
        if (isset($customizations['photo_scale'])) {
            $scale = $customizations['photo_scale'];
            $newWidth = $photo->width() * $scale;
            $newHeight = $photo->height() * $scale;
            $photo->resize($newWidth, $newHeight);
        }

        // Apply rotation
        if (isset($customizations['photo_rotation'])) {
            $rotation = $customizations['photo_rotation'];
            $photo->rotate(-$rotation); // Negative for clockwise
        }

        // Crop/fit to square
        $photo->fit(1080, 1080);

        // Apply position if specified
        if (isset($customizations['photo_position'])) {
            $position = $customizations['photo_position'];
            $offsetX = $position['x'] ?? 0;
            $offsetY = $position['y'] ?? 0;

            // This would require more complex logic to shift the photo
            // For now, we use the fit() method which centers automatically
        }

        // Apply filters if specified
        if (isset($customizations['photo_filter'])) {
            $filter = $customizations['photo_filter'];

            match($filter) {
                'grayscale' => $photo->greyscale(),
                'sepia' => $photo->greyscale()->colorize(30, 15, 0),
                'brightness' => $photo->brightness(20),
                'contrast' => $photo->contrast(15),
                default => null,
            };
        }

        return $photo;
    }

    /**
     * Apply additional customizations (text, stickers).
     */
    protected function applyAdditionalCustomizations($canvas, array $customizations)
    {
        // Add text if provided
        if (isset($customizations['text']) && !empty($customizations['text'])) {
            $text = $customizations['text'];
            $textColor = $customizations['text_color'] ?? '#FFFFFF';
            $textSize = $customizations['text_size'] ?? 36;
            $textPosition = $customizations['text_position'] ?? ['x' => 540, 'y' => 950];

            try {
                $canvas->text($text, $textPosition['x'], $textPosition['y'], function($font) use ($textSize, $textColor) {
                    $fontPath = $this->getFontPath('Roboto-Bold.ttf');

                    if (file_exists($fontPath)) {
                        $font->file($fontPath);
                    }

                    $font->size($textSize);
                    $font->color($textColor);
                    $font->align('center');
                    $font->valign('middle');
                });
            } catch (\Exception $e) {
                \Log::warning("Failed to add text to twibbon", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Add stickers if provided
        if (isset($customizations['stickers']) && is_array($customizations['stickers'])) {
            foreach ($customizations['stickers'] as $sticker) {
                try {
                    // Download sticker from S3 or URL
                    $stickerPath = $this->downloadSticker($sticker['url']);

                    if ($stickerPath) {
                        $stickerImage = Image::make($stickerPath);

                        // Resize sticker if size specified
                        if (isset($sticker['size'])) {
                            $stickerImage->resize($sticker['size'], $sticker['size'], function ($constraint) {
                                $constraint->aspectRatio();
                            });
                        }

                        // Rotate if specified
                        if (isset($sticker['rotation'])) {
                            $stickerImage->rotate(-$sticker['rotation']);
                        }

                        // Insert sticker at position
                        $x = $sticker['x'] ?? 0;
                        $y = $sticker['y'] ?? 0;
                        $canvas->insert($stickerImage, 'top-left', $x, $y);

                        // Clean up
                        unlink($stickerPath);
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to add sticker to twibbon", [
                        'error' => $e->getMessage(),
                        'sticker' => $sticker,
                    ]);
                }
            }
        }

        return $canvas;
    }

    /**
     * Download sticker from URL or S3.
     */
    protected function downloadSticker(string $url): ?string
    {
        try {
            // Check if it's an S3 path
            if (strpos($url, 's3://') === 0 || strpos($url, 'stickers/') !== false) {
                $content = Storage::disk('s3')->get($url);
            } else {
                // Download from external URL
                $content = file_get_contents($url);
            }

            if ($content === false) {
                return null;
            }

            $tempPath = sys_get_temp_dir() . '/' . uniqid('sticker_') . '.png';
            file_put_contents($tempPath, $content);

            return $tempPath;
        } catch (\Exception $e) {
            \Log::warning("Failed to download sticker", [
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

        // Return system font path as fallback
        return '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    }

    /**
     * Clean up temporary files.
     */
    protected function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(\Exception $e): void
    {
        \Log::error("Twibbon generation failed", [
            'user_image_id' => $this->userImage->id,
            'campaign_id' => $this->userImage->campaign_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Mark as failed (you might want to add a status field)
        // For now, we just log the error

        // Re-throw for job retry
        if ($this->attempts() < $this->tries) {
            throw $e;
        }
    }

    /**
     * Handle job failure (after all retries).
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error("Twibbon generation permanently failed", [
            'user_image_id' => $this->userImage->id,
            'attempts' => $this->tries,
            'error' => $exception->getMessage(),
        ]);

        // You could delete the user_generated_image record here
        // or mark it with a failed status if you add that field
    }
}
