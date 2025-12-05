<?php

// ============================================
// File: app/Services/VideoService.php
// ============================================

namespace App\Services;

use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\Point;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class VideoService
{
    protected FFMpeg $ffmpeg;

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => config('ffmpeg.binaries', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => config('ffprobe.binaries', '/usr/bin/ffprobe'),
            'timeout'          => 240,
            'ffmpeg.threads'   => 4,
        ]);
    }

    /**
     * Add overlay to video.
     */
    public function addOverlay(
        string $videoPath,
        ?string $logoPath,
        ?string $textOverlayPath,
        array $parameters = []
    ): string {
        $outputPath = sys_get_temp_dir() . '/' . uniqid('video_') . '.mp4';

        $video = $this->ffmpeg->open($videoPath);

        // If no overlays, just re-encode
        if (!$logoPath && !$textOverlayPath) {
            $format = new X264();
            $format->setKiloBitrate(2000)
                   ->setAudioCodec('aac')
                   ->setAudioKiloBitrate(128);

            $video->save($format, $outputPath);
            return $outputPath;
        }

        // Build FFmpeg command with overlays
        $command = ['-i', $videoPath];

        $filters = [];

        if ($logoPath) {
            $command = array_merge($command, ['-i', $logoPath]);
            $position = $this->getOverlayPosition($parameters['logo_position'] ?? 'center');
            $filters[] = "[0:v][1:v]overlay={$position['x']}:{$position['y']}[v1]";
        }

        if ($textOverlayPath) {
            $baseInput = empty($filters) ? '[0:v]' : '[v1]';
            $command = array_merge($command, ['-i', $textOverlayPath]);
            $filters[] = "{$baseInput}[2:v]overlay=10:10[vout]";
        }

        if (!empty($filters)) {
            $filterComplex = implode(';', $filters);

            $command = array_merge($command, [
                '-filter_complex', $filterComplex,
                '-map', '[vout]',
                '-map', '0:a?',
                '-c:v', 'libx264',
                '-preset', 'medium',
                '-crf', '23',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-movflags', '+faststart',
                '-y',
                $outputPath,
            ]);

            $process = new \Symfony\Component\Process\Process($command);
            $process->setTimeout(240);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception('FFmpeg processing failed: ' . $process->getErrorOutput());
            }
        }

        return $outputPath;
    }

    /**
     * Get overlay position coordinates.
     */
    protected function getOverlayPosition(string $position): array
    {
        $videoWidth = 1080;
        $videoHeight = 1080;
        $logoSize = 200;
        $padding = 20;

        return match($position) {
            'top-left' => ['x' => $padding, 'y' => $padding],
            'top-right' => ['x' => $videoWidth - $logoSize - $padding, 'y' => $padding],
            'bottom-left' => ['x' => $padding, 'y' => $videoHeight - $logoSize - $padding],
            'bottom-right' => ['x' => $videoWidth - $logoSize - $padding, 'y' => $videoHeight - $logoSize - $padding],
            'center' => ['x' => ($videoWidth - $logoSize) / 2, 'y' => ($videoHeight - $logoSize) / 2],
            default => ['x' => ($videoWidth - $logoSize) / 2, 'y' => ($videoHeight - $logoSize) / 2],
        };
    }

    /**
     * Extract video thumbnail.
     */
    public function extractThumbnail(string $videoPath, int $atSecond = 1): string
    {
        $video = $this->ffmpeg->open($videoPath);
        $thumbnailPath = sys_get_temp_dir() . '/' . uniqid('thumb_') . '.jpg';

        $video->frame(TimeCode::fromSeconds($atSecond))
              ->save($thumbnailPath);

        return $thumbnailPath;
    }

    /**
     * Get video duration.
     */
    public function getDuration(string $videoPath): float
    {
        $video = $this->ffmpeg->open($videoPath);
        $ffprobe = \FFMpeg\FFProbe::create([
            'ffprobe.binaries' => config('ffprobe.binaries', '/usr/bin/ffprobe'),
        ]);

        return $ffprobe->format($videoPath)->get('duration');
    }

    /**
     * Convert video format.
     */
    public function convert(string $videoPath, string $format = 'mp4'): string
    {
        $outputPath = sys_get_temp_dir() . '/' . uniqid('converted_') . '.' . $format;

        $video = $this->ffmpeg->open($videoPath);

        $videoFormat = new X264();
        $videoFormat->setKiloBitrate(2000)
                    ->setAudioCodec('aac')
                    ->setAudioKiloBitrate(128);

        $video->save($videoFormat, $outputPath);

        return $outputPath;
    }

    /**
     * Resize video.
     */
    public function resize(string $videoPath, int $width, int $height): string
    {
        $outputPath = sys_get_temp_dir() . '/' . uniqid('resized_') . '.mp4';

        $video = $this->ffmpeg->open($videoPath);

        $video->filters()
              ->resize(new Dimension($width, $height))
              ->synchronize();

        $format = new X264();
        $video->save($format, $outputPath);

        return $outputPath;
    }

    /**
     * Create video from images (slideshow).
     */
    public function createSlideshow(array $imagePaths, int $duration = 3): string
    {
        // This would require a more complex FFmpeg command
        // For now, just a placeholder
        throw new \Exception('Slideshow creation not yet implemented');
    }

    /**
     * Trim video.
     */
    public function trim(string $videoPath, int $startSecond, int $durationSeconds): string
    {
        $outputPath = sys_get_temp_dir() . '/' . uniqid('trimmed_') . '.mp4';

        $video = $this->ffmpeg->open($videoPath);

        $video->clip(
            TimeCode::fromSeconds($startSecond),
            TimeCode::fromSeconds($durationSeconds)
        );

        $format = new X264();
        $video->save($format, $outputPath);

        return $outputPath;
    }
}
