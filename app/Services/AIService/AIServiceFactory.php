<?php

// ============================================
// File: app/Services/AIService/AIServiceFactory.php
// ============================================

namespace App\Services\AIService;

use App\Models\AiProvider;

class AIServiceFactory
{
    /**
     * Create AI service instance from provider.
     */
    public static function make(AiProvider $provider): AIServiceInterface
    {
        return match($provider->slug) {
            'openai' => new OpenAIService(),
            'stability' => new StabilityAIService(),
            'leonardo' => new LeonardoAIService(),
            default => throw new \Exception("Unknown AI provider: {$provider->slug}"),
        };
    }

    /**
     * Get available AI service.
     */
    public static function getAvailable(): ?AIServiceInterface
    {
        $provider = AiProvider::active()
            ->byPriority()
            ->first();

        if (!$provider) {
            return null;
        }

        $service = self::make($provider);

        return $service->isAvailable() ? $service : null;
    }
}
