<?php

// ============================================
// File: app/Services/AIService/StabilityAIService.php
// ============================================

namespace App\Services\AIService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StabilityAIService implements AIServiceInterface
{
    protected string $apiKey;
    protected string $engineId = 'stable-diffusion-xl-1024-v1-0';
    protected int $timeout = 60;

    public function __construct()
    {
        $this->apiKey = config('services.stability.key');
    }

    /**
     * Generate images using Stability AI.
     */
    public function generate(string $prompt, array $parameters = []): array
    {
        $samples = $parameters['count'] ?? 4;
        $cfgScale = $parameters['cfg_scale'] ?? 7;
        $steps = $parameters['steps'] ?? 30;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post(
                "https://api.stability.ai/v1/generation/{$this->engineId}/text-to-image",
                [
                    'text_prompts' => [
                        [
                            'text' => $this->enhancePrompt($prompt, $parameters),
                            'weight' => 1
                        ],
                        [
                            'text' => 'blurry, bad quality, distorted, low resolution, watermark',
                            'weight' => -1
                        ]
                    ],
                    'cfg_scale' => $cfgScale,
                    'height' => 1024,
                    'width' => 1024,
                    'samples' => min($samples, 10),
                    'steps' => $steps,
                    'style_preset' => $parameters['style_preset'] ?? null,
                ]
            );

            if (!$response->successful()) {
                throw new \Exception('Stability AI error: ' . $response->body());
            }

            $data = $response->json();

            return array_map(function($artifact) {
                // Decode base64 image and upload to S3
                $imageData = base64_decode($artifact['base64']);
                $s3Path = 'ai-generations/stability/' . uniqid() . '.png';

                Storage::disk('s3')->put($s3Path, $imageData);

                return [
                    'url' => Storage::disk('s3')->url($s3Path),
                    'path' => $s3Path,
                    'seed' => $artifact['seed'] ?? null,
                    'provider' => 'stability',
                ];
            }, $data['artifacts']);

        } catch (\Exception $e) {
            Log::error('Stability AI generation failed', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);
            throw $e;
        }
    }

    protected function enhancePrompt(string $prompt, array $parameters): string
    {
        $enhanced = $prompt;
        $enhanced .= ", transparent background, PNG format, ";
        $enhanced .= "frame overlay design for social media profile picture, ";
        $enhanced .= "center space for photo, professional quality, ";
        $enhanced .= "clean design, modern aesthetic, ";
        $enhanced .= "high detail, sharp edges";

        return $enhanced;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'Stability AI SDXL';
    }

    public function getRateLimit(): int
    {
        return 100; // requests per hour
    }
}
