<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Notifications\AIGenerationCompletedNotification;
use App\Notifications\AIGenerationFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class GenerateAIFrameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [10, 30, 60];

    protected AiGeneration $generation;

    /**
     * Create a new job instance.
     */
    public function __construct(AiGeneration $generation)
    {
        $this->generation = $generation;
        $this->onQueue('ai');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Mark as processing
            $this->generation->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $startTime = microtime(true);

            // Get AI provider
            $provider = $this->generation->aiProvider;

            if (!$provider || !$provider->canProcess()) {
                throw new \Exception('AI provider is not available');
            }

            // Build prompt with enhancements
            $enhancedPrompt = $this->buildEnhancedPrompt(
                $this->generation->prompt,
                $this->generation->parameters
            );

            // Generate images based on provider
            $results = match($provider->slug) {
                'openai' => $this->generateWithOpenAI($enhancedPrompt, $provider),
                'stability' => $this->generateWithStability($enhancedPrompt, $provider),
                'leonardo' => $this->generateWithLeonardo($enhancedPrompt, $provider),
                default => throw new \Exception('Unknown AI provider'),
            };

            // Process and save results
            $processedResults = $this->processResults($results);

            // Calculate generation time
            $generationTime = round(microtime(true) - $startTime, 2);

            // Update generation record
            $this->generation->update([
                'status' => 'completed',
                'results' => $processedResults,
                'generation_time' => $generationTime,
                'completed_at' => now(),
            ]);

            // Update provider usage
            $provider->incrementUsage();

            // Send notification
            $this->generation->user->notify(
                new AIGenerationCompletedNotification($this->generation)
            );

            \Log::info("AI generation completed", [
                'generation_id' => $this->generation->id,
                'time' => $generationTime,
                'results_count' => count($processedResults),
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Generate with OpenAI DALL-E.
     */
    protected function generateWithOpenAI(string $prompt, $provider): array
    {
        $count = $this->generation->parameters['count'] ?? 4;
        $size = $this->generation->parameters['size'] ?? '1024x1024';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $provider->getApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/images/generations', [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => min($count, 1), // DALL-E 3 only supports n=1
            'size' => $size,
            'quality' => 'standard',
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
            ];
        }, $data['data']);
    }

    /**
     * Generate with Stability AI.
     */
    protected function generateWithStability(string $prompt, $provider): array
    {
        $count = $this->generation->parameters['count'] ?? 4;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $provider->getApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image', [
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1],
            ],
            'cfg_scale' => 7,
            'height' => 1024,
            'width' => 1024,
            'samples' => $count,
            'steps' => 30,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Stability AI error: ' . $response->body());
        }

        $data = $response->json();

        return array_map(function($artifact) {
            // Decode base64 image
            $imageData = base64_decode($artifact['base64']);
            $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.png';
            file_put_contents($tempPath, $imageData);

            // Upload to S3
            $s3Path = 'ai-generations/' . uniqid() . '.png';
            Storage::disk('s3')->put($s3Path, file_get_contents($tempPath));
            unlink($tempPath);

            return [
                'url' => Storage::disk('s3')->url($s3Path),
                'path' => $s3Path,
            ];
        }, $data['artifacts']);
    }

    /**
     * Generate with Leonardo AI.
     */
    protected function generateWithLeonardo(string $prompt, $provider): array
    {
        $count = $this->generation->parameters['count'] ?? 4;

        // Start generation
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $provider->getApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://cloud.leonardo.ai/api/rest/v1/generations', [
            'prompt' => $prompt,
            'num_images' => $count,
            'width' => 1024,
            'height' => 1024,
            'modelId' => 'b24e16ff-06e3-43eb-8d33-4416c2d75876', // Leonardo Creative
        ]);

        if (!$response->successful()) {
            throw new \Exception('Leonardo AI error: ' . $response->body());
        }

        $generationId = $response->json()['sdGenerationJob']['generationId'];

        // Poll for results (Leonardo is async)
        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(2);

            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $provider->getApiKey(),
            ])->get("https://cloud.leonardo.ai/api/rest/v1/generations/{$generationId}");

            if ($statusResponse->successful()) {
                $status = $statusResponse->json();

                if ($status['generations_by_pk']['status'] === 'COMPLETE') {
                    return array_map(function($image) {
                        return [
                            'url' => $image['url'],
                        ];
                    }, $status['generations_by_pk']['generated_images']);
                }
            }

            $attempt++;
        }

        throw new \Exception('Leonardo AI generation timeout');
    }

    /**
     * Build enhanced prompt with best practices.
     */
    protected function buildEnhancedPrompt(string $userPrompt, array $parameters): string
    {
        $prompt = $userPrompt;

        // Add style enhancements
        if (isset($parameters['style'])) {
            $prompt .= ", {$parameters['style']} style";
        }

        // Add color preferences
        if (isset($parameters['colors']) && is_array($parameters['colors'])) {
            $colors = implode(', ', $parameters['colors']);
            $prompt .= ", using colors: {$colors}";
        }

        // Add frame-specific enhancements
        $prompt .= ", transparent background, PNG format, 1080x1080 frame overlay";
        $prompt .= ", high quality, professional design, clean edges";

        return $prompt;
    }

    /**
     * Process and save generated images.
     */
    protected function processResults(array $results): array
    {
        $processed = [];

        foreach ($results as $index => $result) {
            try {
                // Download image if URL
                if (isset($result['url']) && !isset($result['path'])) {
                    $imageContent = file_get_contents($result['url']);

                    if ($imageContent === false) {
                        \Log::warning("Failed to download image from URL: {$result['url']}");
                        continue;
                    }

                    // Save to temporary file
                    $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.png';
                    file_put_contents($tempPath, $imageContent);

                    // Process image (ensure transparent PNG, resize if needed)
                    $image = Image::make($tempPath);

                    // Resize to 1080x1080 if needed
                    if ($image->width() != 1080 || $image->height() != 1080) {
                        $image->fit(1080, 1080);
                    }

                    // Save processed image
                    $image->save($tempPath, 90, 'png');

                    // Upload to S3
                    $s3Path = 'ai-generations/' . $this->generation->id . '_' . $index . '.png';
                    Storage::disk('s3')->put($s3Path, file_get_contents($tempPath));

                    // Generate thumbnail
                    $thumbnail = Image::make($tempPath)->fit(300, 300);
                    $thumbnailPath = 'ai-generations/thumbnails/' . $this->generation->id . '_' . $index . '.png';
                    Storage::disk('s3')->put($thumbnailPath, $thumbnail->encode('png'));

                    // Clean up
                    unlink($tempPath);

                    $result['path'] = $s3Path;
                    $result['url'] = Storage::disk('s3')->url($s3Path);
                    $result['thumbnail'] = Storage::disk('s3')->url($thumbnailPath);
                }

                $processed[] = [
                    'index' => $index,
                    'url' => $result['url'],
                    'path' => $result['path'] ?? null,
                    'thumbnail' => $result['thumbnail'] ?? null,
                    'revised_prompt' => $result['revised_prompt'] ?? null,
                ];

            } catch (\Exception $e) {
                \Log::error("Error processing AI result {$index}", [
                    'error' => $e->getMessage(),
                    'generation_id' => $this->generation->id,
                ]);
            }
        }

        if (empty($processed)) {
            throw new \Exception('No images could be processed successfully');
        }

        return $processed;
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(\Exception $e): void
    {
        $this->generation->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);

        // Notify user
        $this->generation->user->notify(
            new AIGenerationFailedNotification($this->generation, $e->getMessage())
        );

        \Log::error("AI generation failed", [
            'generation_id' => $this->generation->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

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
        $this->generation->update([
            'status' => 'failed',
            'error_message' => 'Failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);

        \Log::error("AI generation permanently failed", [
            'generation_id' => $this->generation->id,
            'attempts' => $this->tries,
            'error' => $exception->getMessage(),
        ]);
    }
}
