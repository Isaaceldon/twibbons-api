<?php
// ============================================
// Usage Examples in Controllers
// ============================================

/*
// In AIGenerationController after successful generation:
$generation->user->notify(new AIGenerationCompletedNotification($generation));

// In AIGenerationController after failed generation:
$generation->user->notify(new AIGenerationFailedNotification($generation, $errorMessage));

// In VideoJobController after completion:
$videoJob->user->notify(new VideoJobCompletedNotification($videoJob));

// In CampaignController after publishing:
$campaign->user->notify(new CampaignPublishedNotification($campaign));

// When user reaches AI limit:
$user->notify(new MonthlyAILimitReachedNotification(3));
*/


// ============================================
// USAGE EXAMPLE
// ============================================

/*
// In GenerateAIFrameJob or Controller:

use App\Services\AIService\AIServiceFactory;

// Get available service
$service = AIServiceFactory::getAvailable();

if (!$service) {
    throw new \Exception('No AI service available');
}

// Generate images
$results = $service->generate($prompt, [
    'count' => 4,
    'size' => '1024x1024',
    'style' => 'vivid',
]);

// Or create from specific provider
$provider = AiProvider::where('slug', 'openai')->first();
$service = AIServiceFactory::make($provider);
$results = $service->generate($prompt);
*/



// ============================================
// USAGE EXAMPLES
// ============================================

/*
// In Controller or Job:

// Image Processing
$imageService = app(ImageProcessingService::class);
$result = $imageService->combinePhotoWithFrame($photoPath, $framePath, $customizations);

// Video Processing
$videoService = app(VideoService::class);
$result = $videoService->addOverlay($videoPath, $logoPath, $textPath);

// QR Code Generation
$qrService = app(QRCodeService::class);
$qrPath = $qrService->generateForCampaign($campaign->slug, $logoPath);

// Statistics
$statsService = app(StatisticsService::class);
$metrics = $statsService->getCampaignMetrics($campaign);
$statsService->aggregateDailyStatistics();

// AI Generation
use App\Services\AIService\AIServiceFactory;
$aiService = AIServiceFactory::getAvailable();
$results = $aiService->generate($prompt, $parameters);
*/
