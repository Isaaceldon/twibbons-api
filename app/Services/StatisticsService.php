<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\StatisticsDaily;
use App\Models\GlobalStatistics;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * Aggregate daily statistics for all campaigns.
     */
    public function aggregateDailyStatistics(\DateTime $date = null): void
    {
        $date = $date ?? now()->subDay();
        $dateString = $date->format('Y-m-d');

        $campaigns = Campaign::active()->published()->get();

        foreach ($campaigns as $campaign) {
            $stats = DB::table('user_generated_images')
                ->where('campaign_id', $campaign->id)
                ->whereDate('created_at', $dateString)
                ->selectRaw('
                    COUNT(*) as generations,
                    SUM(downloads_count) as downloads
                ')
                ->first();

            $shares = DB::table('shares')
                ->whereIn('user_generated_image_id', function ($query) use ($campaign, $dateString) {
                    $query->select('id')
                        ->from('user_generated_images')
                        ->where('campaign_id', $campaign->id)
                        ->whereDate('created_at', $dateString);
                })
                ->count();

            StatisticsDaily::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'stats_date' => $date,
                ],
                [
                    'views' => 0, // Views would need separate tracking
                    'generations' => $stats->generations ?? 0,
                    'downloads' => $stats->downloads ?? 0,
                    'shares' => $shares,
                    'details' => [],
                ]
            );
        }
    }

    /**
     * Aggregate global statistics.
     */
    public function aggregateGlobalStatistics(\DateTime $date = null): void
    {
        $date = $date ?? now();

        GlobalStatistics::updateOrCreate(
            ['stats_date' => $date->format('Y-m-d')],
            [
                'total_users' => User::count(),
                'active_users' => User::active()->count(),
                'total_campaigns' => Campaign::count(),
                'active_campaigns' => Campaign::active()->count(),
                'total_generations' => DB::table('user_generated_images')->count(),
                'total_downloads' => DB::table('user_generated_images')->sum('downloads_count'),
                'total_ai_generations' => DB::table('ai_generations')->count(),
                'total_video_jobs' => DB::table('video_jobs')->count(),
            ]
        );
    }

    /**
     * Get campaign performance metrics.
     */
    public function getCampaignMetrics(Campaign $campaign): array
    {
        $cacheKey = "campaign_metrics_{$campaign->id}";

        return Cache::remember($cacheKey, 3600, function () use ($campaign) {
            $thirtyDaysAgo = now()->subDays(30);

            return [
                'total_views' => $campaign->views_count,
                'total_generations' => $campaign->userImages()->count(),
                'total_downloads' => $campaign->userImages()->sum('downloads_count'),
                'total_shares' => DB::table('shares')
                    ->whereIn('user_generated_image_id', function ($query) use ($campaign) {
                        $query->select('id')
                            ->from('user_generated_images')
                            ->where('campaign_id', $campaign->id);
                    })->count(),
                'last_30_days' => [
                    'generations' => $campaign->userImages()
                        ->where('created_at', '>=', $thirtyDaysAgo)
                        ->count(),
                    'downloads' => $campaign->userImages()
                        ->where('created_at', '>=', $thirtyDaysAgo)
                        ->sum('downloads_count'),
                ],
                'conversion_rate' => $this->calculateConversionRate($campaign),
                'engagement_score' => $this->calculateEngagementScore($campaign),
            ];
        });
    }

    /**
     * Calculate conversion rate (generations / views).
     */
    protected function calculateConversionRate(Campaign $campaign): float
    {
        if ($campaign->views_count == 0) {
            return 0;
        }

        $generations = $campaign->userImages()->count();
        return round(($generations / $campaign->views_count) * 100, 2);
    }

    /**
     * Calculate engagement score.
     */
    protected function calculateEngagementScore(Campaign $campaign): float
    {
        $generations = $campaign->userImages()->count();
        $downloads = $campaign->userImages()->sum('downloads_count');
        $shares = DB::table('shares')
            ->whereIn('user_generated_image_id', function ($query) use ($campaign) {
                $query->select('id')
                    ->from('user_generated_images')
                    ->where('campaign_id', $campaign->id);
            })->count();

        // Weighted score: generations (1x), downloads (2x), shares (3x)
        $score = ($generations * 1) + ($downloads * 2) + ($shares * 3);

        return round($score / 100, 2); // Normalize
    }

    /**
     * Get trending campaigns.
     */
    public function getTrendingCampaigns(int $days = 7, int $limit = 10): array
    {
        $cacheKey = "trending_campaigns_{$days}_{$limit}";

        return Cache::remember($cacheKey, 1800, function () use ($days, $limit) {
            $startDate = now()->subDays($days);

            return Campaign::public()
                ->active()
                ->published()
                ->withCount(['userImages' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }])
                ->having('user_images_count', '>', 0)
                ->orderBy('user_images_count', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Clear statistics cache.
     */
    public function clearCache(?string $campaignId = null): void
    {
        if ($campaignId) {
            Cache::forget("campaign_metrics_{$campaignId}");
            Cache::forget("realtime_stats_{$campaignId}");
        } else {
            Cache::flush();
        }
    }
}
