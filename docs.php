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
