<?php

// ============================================
// File: app/Services/AIService/AIServiceInterface.php
// ============================================

namespace App\Services\AIService;

interface AIServiceInterface
{
    /**
     * Generate images from prompt.
     *
     * @param string $prompt
     * @param array $parameters
     * @return array
     */
    public function generate(string $prompt, array $parameters = []): array;

    /**
     * Check if service is available.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get service name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get service rate limit.
     *
     * @return int
     */
    public function getRateLimit(): int;
}

// ============================================
// File: app/Services/AIService/OpenAIService.php
// ============================================

namespace App\Services\AIService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService implements AIServiceInterface
{
    protected string $apiKey;
    protected string $model = 'dall-e-3';
    protected int $timeout = 60;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
    }

    /**
     * Generate images using DALL-E 3.
     */
    public function generate(string $prompt, array $parameters = []): array
    {
        $size = $parameters['size'] ?? '1024x1024';
        $quality = $parameters['quality'] ?? 'standard';
        $style = $parameters['style'] ?? 'vivid';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post('https://api.openai.com/v1/images/generations', [
                'model' => $this->model,
                'prompt' => $this->enhancePrompt($prompt, $parameters),
                'n' => 1, // DALL-E 3 only supports n=1
                'size' => $size,
                'quality' => $quality,
                'style' => $style,
                'response_format' => 'url',
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->body());
            }

            $data = $response->json();

            return array_map(function($image) {
                return [
                    'url' => $image['url'],
                    'revised_prompt' => $image['revised_prompt'] ?? null,
                    'provider' => 'openai',
                ];
            }, $data['data']);

        } catch (\Exception $e) {
            Log::error('OpenAI generation failed', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);
            throw $e;
        }
    }

    /**
     * Enhance prompt with best practices.
     */
    protected function enhancePrompt(string $prompt, array $parameters): string
    {
        $enhanced = $prompt;

        // Add frame-specific instructions
        $enhanced .= ". Create a frame overlay design with transparent background, ";
        $enhanced .= "suitable for social media profile pictures. ";
        $enhanced .= "The design should have clear space in the center for user photos. ";
        $enhanced .= "High quality, professional, modern aesthetic. ";
        $enhanced .= "PNG format with transparency.";

        // Add style if specified
        if (isset($parameters['art_style'])) {
            $enhanced .= " Art style: " . $parameters['art_style'] . ".";
        }

        // Add colors if specified
        if (isset($parameters['colors']) && is_array($parameters['colors'])) {
            $colors = implode(', ', $parameters['colors']);
            $enhanced .= " Use these colors: {$colors}.";
        }

        return $enhanced;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'OpenAI DALL-E 3';
    }

    public function getRateLimit(): int
    {
        return 50; // requests per hour
    }
}
