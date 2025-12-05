<?php
namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class QRCodeService
{
    /**
     * Generate QR code for campaign.
     */
    public function generateForCampaign(string $campaignSlug, ?string $logoPath = null): string
    {
        $url = url("/campaign/{$campaignSlug}");
        $filename = "qr-codes/{$campaignSlug}-" . uniqid() . ".png";
        $fullPath = storage_path("app/public/{$filename}");

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($logoPath) {
            // QR code with logo
            QrCode::format('png')
                  ->size(500)
                  ->margin(2)
                  ->errorCorrection('H')
                  ->merge($logoPath, 0.3, true)
                  ->generate($url, $fullPath);
        } else {
            // Simple QR code
            QrCode::format('png')
                  ->size(500)
                  ->margin(2)
                  ->errorCorrection('H')
                  ->generate($url, $fullPath);
        }

        // Upload to S3
        $s3Path = "qr-codes/{$campaignSlug}.png";
        Storage::disk('s3')->put($s3Path, file_get_contents($fullPath));

        // Clean up local file
        unlink($fullPath);

        return $s3Path;
    }

    /**
     * Generate styled QR code.
     */
    public function generateStyled(
        string $url,
        string $filename,
        array $options = []
    ): string {
        $size = $options['size'] ?? 500;
        $backgroundColor = $options['background_color'] ?? '#FFFFFF';
        $foregroundColor = $options['foreground_color'] ?? '#000000';
        $margin = $options['margin'] ?? 2;

        $fullPath = storage_path("app/public/qr-codes/{$filename}");

        QrCode::format('png')
              ->size($size)
              ->margin($margin)
              ->backgroundColor(...$this->hexToRgb($backgroundColor))
              ->color(...$this->hexToRgb($foregroundColor))
              ->errorCorrection('H')
              ->generate($url, $fullPath);

        return "qr-codes/{$filename}";
    }

    /**
     * Generate QR code with custom design.
     */
    public function generateCustom(
        string $url,
        string $filename,
        ?string $logoPath = null,
        array $colors = []
    ): string {
        $primaryColor = $colors['primary'] ?? '#000000';
        $backgroundColor = $colors['background'] ?? '#FFFFFF';

        $qr = QrCode::format('png')
                    ->size(500)
                    ->margin(2)
                    ->backgroundColor(...$this->hexToRgb($backgroundColor))
                    ->color(...$this->hexToRgb($primaryColor))
                    ->errorCorrection('H');

        if ($logoPath && file_exists($logoPath)) {
            $qr->merge($logoPath, 0.25, true);
        }

        $fullPath = storage_path("app/public/qr-codes/{$filename}");
        $qr->generate($url, $fullPath);

        return "qr-codes/{$filename}";
    }

    /**
     * Convert hex color to RGB array.
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Generate QR code SVG.
     */
    public function generateSvg(string $url): string
    {
        return QrCode::format('svg')
                     ->size(500)
                     ->generate($url);
    }
}

