<?php
namespace Database\Seeders;

use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AIProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'OpenAI DALL-E 3',
                'slug' => 'openai',
                'api_endpoint' => 'https://api.openai.com/v1/images/generations',
                'api_key_encrypted' => encrypt(config('services.openai.key')),
                'configuration' => [
                    'model' => 'dall-e-3',
                    'max_images_per_request' => 1,
                ],
                'is_active' => true,
                'priority' => 1,
                'rate_limit' => 50,
            ],
            [
                'name' => 'Stability AI SDXL',
                'slug' => 'stability',
                'api_endpoint' => 'https://api.stability.ai/v1/generation',
                'api_key_encrypted' => encrypt(config('services.stability.key')),
                'configuration' => [
                    'engine_id' => 'stable-diffusion-xl-1024-v1-0',
                    'max_images_per_request' => 10,
                ],
                'is_active' => true,
                'priority' => 2,
                'rate_limit' => 100,
            ],
            [
                'name' => 'Leonardo AI',
                'slug' => 'leonardo',
                'api_endpoint' => 'https://cloud.leonardo.ai/api/rest/v1/generations',
                'api_key_encrypted' => encrypt(config('services.leonardo.key')),
                'configuration' => [
                    'model_id' => 'b24e16ff-06e3-43eb-8d33-4416c2d75876',
                    'max_images_per_request' => 8,
                ],
                'is_active' => false, // Disabled by default
                'priority' => 3,
                'rate_limit' => 80,
            ],
        ];

        foreach ($providers as $provider) {
            AiProvider::create($provider);
        }
    }
}
