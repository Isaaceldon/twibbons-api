<?php
// ============================================
// File: app/Services/AIService/LeonardoAIService.php
// ============================================

namespace App\Services\AIService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeonardoAIService implements AIServiceInterface
{
    protected string $apiKey;
    protected string $modelId = 'b24e16ff-06e3-43eb-8d33-4416c2d75876'; // Leonardo Creative
    protected int $timeout = 60;

    public function __construct()
    {
        $this->apiKey = config('services.leonardo.key');
    }

    /**
     * Generate images using Leonardo AI.
     */
    public function generate(string $prompt, array $parameters = []): array
    {
        $numImages = $parameters['count'] ?? 4;

        try {
            // Start generation
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)->post(
                'https://cloud.leonardo.ai/api/rest/v1/generations',
                [
                    'prompt' => $this->enhancePrompt($prompt, $parameters),
                    'negative_prompt' => 'blurry, bad quality, distorted, watermark, text',
                    'modelId' => $this->modelId,
                    'num_images' => min($numImages, 8),
                    'width' => 1024,
                    'height' => 1024,
                    'guidance_scale' => $parameters['guidance_scale'] ?? 7,
                    'num_inference_steps' => $parameters['steps'] ?? 30,
                ]
            );

            if (!$response->successful()) {
                throw new \Exception('Leonardo AI error: ' . $response->body());
            }

            $generationId = $response->json()['sdGenerationJob']['generationId'];

            // Poll for completion (Leonardo is async)
            return $this->pollGeneration($generationId);

        } catch (\Exception $e) {
            Log::error('Leonardo AI generation failed', [
                'error' => $e->getMessage(),
                'prompt' => $prompt,
            ]);
            throw $e;
        }
    }

    /**
     * Poll Leonardo generation status.
     */
    protected function pollGeneration(string $generationId, int $maxAttempts = 30): array
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(2);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get("https://cloud.leonardo.ai/api/rest/v1/generations/{$generationId}");

            if ($response->successful()) {
                $data = $response->json();
                $generation = $data['generations_by_pk'];

                if ($generation['status'] === 'COMPLETE') {
                    return array_map(function($image) {
                        return [
                            'url' => $image['url'],
                            'id' => $image['id'],
                            'provider' => 'leonardo',
                        ];
                    }, $generation['generated_images']);
                }

                if ($generation['status'] === 'FAILED') {
                    throw new \Exception('Leonardo generation failed');
                }
            }

            $attempt++;
        }

        throw new \Exception('Leonardo generation timeout after ' . $maxAttempts . ' attempts');
    }

    protected function enhancePrompt(string $prompt, array $parameters): string
    {
        $enhanced = $prompt;
        $enhanced .= ", transparent background, frame overlay, ";
        $enhanced .= "social media profile picture frame, ";
        $enhanced .= "center space for photo, clean design, ";
        $enhanced .= "professional quality, modern style";

        return $enhanced;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'Leonardo AI';
    }

    public function getRateLimit(): int
    {
        return 80; // requests per hour
    }
}
