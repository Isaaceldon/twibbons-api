<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignStatistics;
use App\Models\GlobalStatistics;
use App\Models\StatisticsDaily;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Get global platform statistics (admin only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function global(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $cacheKey = 'global_stats_' . now()->format('Y-m-d-H');

            $stats = Cache::remember($cacheKey, 3600, function () {
                return [
                    'users' => [
                        'total' => User::count(),
                        'active' => User::active()->count(),
                        'verified' => User::verified()->count(),
                        'new_today' => User::whereDate('created_at', today())->count(),
                        'new_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                        'new_this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                    ],
                    'campaigns' => [
                        'total' => Campaign::count(),
                        'active' => Campaign::active()->count(),
                        'published' => Campaign::published()->count(),
                        'public' => Campaign::public()->count(),
                        'new_today' => Campaign::whereDate('created_at', today())->count(),
                        'new_this_week' => Campaign::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                        'new_this_month' => Campaign::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                        'by_category' => Campaign::selectRaw('category, count(*) as count')
                            ->groupBy('category')
                            ->pluck('count', 'category'),
                    ],
                    'engagement' => [
                        'total_views' => Campaign::sum('views_count'),
                        'total_generations' => DB::table('user_generated_images')->count(),
                        'total_downloads' => DB::table('user_generated_images')->sum('downloads_count'),
                        'total_shares' => DB::table('shares')->count(),
                    ],
                    'ai' => [
                        'total_generations' => DB::table('ai_generations')->count(),
                        'completed' => DB::table('ai_generations')->where('status', 'completed')->count(),
                        'failed' => DB::table('ai_generations')->where('status', 'failed')->count(),
                        'average_time' => DB::table('ai_generations')
                            ->where('status', 'completed')
                            ->avg('generation_time'),
                    ],
                    'videos' => [
                        'total_jobs' => DB::table('video_jobs')->count(),
                        'completed' => DB::table('video_jobs')->where('status', 'completed')->count(),
                        'pending' => DB::table('video_jobs')->whereIn('status', ['pending', 'processing'])->count(),
                        'failed' => DB::table('video_jobs')->where('status', 'failed')->count(),
                        'average_time' => DB::table('video_jobs')
                            ->where('status', 'completed')
                            ->avg('processing_time'),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch global statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign statistics.
     *
     * @param string $campaignId
     * @return JsonResponse
     */
    public function campaign(string $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::with('statistics')->findOrFail($campaignId);

            // Check authorization
            if (!$campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin() && $campaign->visibility !== 'public') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view these statistics'
                ], 403);
            }

            $statistics = $campaign->statistics;

            if (!$statistics) {
                return response()->json([
                    'success' => false,
                    'message' => 'No statistics available for this campaign'
                ], 404);
            }

            $data = [
                'overview' => [
                    'total_views' => $statistics->total_views,
                    'total_generations' => $statistics->total_generations,
                    'total_downloads' => $statistics->total_downloads,
                    'total_shares' => $statistics->total_shares,
                ],
                'demographics' => [
                    'countries' => $statistics->countries ?? [],
                    'top_countries' => $statistics->getTopCountries(10),
                    'devices' => $statistics->devices ?? [],
                ],
                'social' => [
                    'platforms' => $statistics->platforms ?? [],
                ],
                'referrers' => $statistics->top_referrers ?? [],
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaign statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign daily statistics (time series).
     *
     * @param Request $request
     * @param string $campaignId
     * @return JsonResponse
     */
    public function campaignDaily(Request $request, string $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);

            // Check authorization
            if (!$campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view these statistics'
                ], 403);
            }

            $days = $request->get('days', 30);
            $startDate = now()->subDays($days)->startOfDay();

            $dailyStats = StatisticsDaily::where('campaign_id', $campaignId)
                ->where('stats_date', '>=', $startDate)
                ->orderBy('stats_date', 'asc')
                ->get();

            $chartData = [
                'labels' => $dailyStats->pluck('stats_date')->map(fn($date) => $date->format('Y-m-d'))->toArray(),
                'datasets' => [
                    [
                        'label' => 'Views',
                        'data' => $dailyStats->pluck('views')->toArray(),
                    ],
                    [
                        'label' => 'Generations',
                        'data' => $dailyStats->pluck('generations')->toArray(),
                    ],
                    [
                        'label' => 'Downloads',
                        'data' => $dailyStats->pluck('downloads')->toArray(),
                    ],
                    [
                        'label' => 'Shares',
                        'data' => $dailyStats->pluck('shares')->toArray(),
                    ],
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'days' => $days,
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => now()->format('Y-m-d'),
                    ],
                    'chart_data' => $chartData,
                    'summary' => [
                        'total_views' => $dailyStats->sum('views'),
                        'total_generations' => $dailyStats->sum('generations'),
                        'total_downloads' => $dailyStats->sum('downloads'),
                        'total_shares' => $dailyStats->sum('shares'),
                        'average_daily_views' => round($dailyStats->avg('views'), 2),
                        'peak_day' => $dailyStats->sortByDesc('views')->first()?->stats_date->format('Y-m-d'),
                    ],
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch daily statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user dashboard statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function userDashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first(),
                    'member_since' => $user->created_at->format('Y-m-d'),
                ],
                'campaigns' => [
                    'total' => $user->campaigns()->count(),
                    'active' => $user->getActiveCampaignsCount(),
                    'total_views' => $user->getTotalViews(),
                    'most_popular' => $user->getMostPopularCampaign()?->only(['id', 'title', 'slug', 'views_count']),
                    'recent' => $user->getRecentCampaigns(5)->map(function($campaign) {
                        return [
                            'id' => $campaign->id,
                            'title' => $campaign->title,
                            'slug' => $campaign->slug,
                            'views_count' => $campaign->views_count,
                            'created_at' => $campaign->created_at,
                        ];
                    }),
                ],
                'engagement' => [
                    'total_generations' => $user->getTotalGenerations(),
                    'total_downloads' => $user->getTotalDownloads(),
                ],
                'ai' => [
                    'total_generations' => $user->getAIGenerationsCount(),
                    'this_month' => $user->getAIGenerationsThisMonth(),
                    'remaining' => $user->getRemainingAIGenerations(),
                ],
                'brand_kits' => [
                    'total' => $user->brand_kits()->count(),
                    'default' => $user->getDefaultBrandKit()?->only(['id', 'name']),
                ],
                'videos' => [
                    'total' => $user->video_jobs()->count(),
                    'completed' => $user->video_jobs()->where('status', 'completed')->count(),
                    'pending' => $user->getPendingVideoJobs()->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trending campaigns.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function trending(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);
            $limit = $request->get('limit', 10);

            $trending = Campaign::public()
                ->active()
                ->published()
                ->with('user:id,name,email')
                ->withCount(['userImages' => function($query) use ($days) {
                    $query->where('created_at', '>=', now()->subDays($days));
                }])
                ->having('user_images_count', '>', 0)
                ->orderBy('user_images_count', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($campaign) {
                    return [
                        'id' => $campaign->id,
                        'title' => $campaign->title,
                        'slug' => $campaign->slug,
                        'category' => $campaign->category,
                        'views_count' => $campaign->views_count,
                        'generations_count' => $campaign->user_images_count,
                        'user' => $campaign->user,
                        'created_at' => $campaign->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'days' => $days,
                        'start_date' => now()->subDays($days)->format('Y-m-d'),
                    ],
                    'campaigns' => $trending,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch trending campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export campaign statistics to CSV.
     *
     * @param Request $request
     * @param string $campaignId
     * @return mixed
     */
    public function exportCsv(Request $request, string $campaignId)
    {
        try {
            $campaign = Campaign::with('statistics')->findOrFail($campaignId);

            // Check authorization
            if (!$campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to export these statistics'
                ], 403);
            }

            $days = $request->get('days', 30);
            $startDate = now()->subDays($days);

            $dailyStats = StatisticsDaily::where('campaign_id', $campaignId)
                ->where('stats_date', '>=', $startDate)
                ->orderBy('stats_date', 'asc')
                ->get();

            // Generate CSV
            $filename = "campaign-{$campaign->slug}-statistics-" . now()->format('Y-m-d') . ".csv";
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($campaign, $dailyStats) {
                $file = fopen('php://output', 'w');

                // Header row
                fputcsv($file, ['Campaign Statistics Export']);
                fputcsv($file, ['Campaign', $campaign->title]);
                fputcsv($file, ['Exported', now()->format('Y-m-d H:i:s')]);
                fputcsv($file, []);

                // Data headers
                fputcsv($file, ['Date', 'Views', 'Generations', 'Downloads', 'Shares']);

                // Data rows
                foreach ($dailyStats as $stat) {
                    fputcsv($file, [
                        $stat->stats_date->format('Y-m-d'),
                        $stat->views,
                        $stat->generations,
                        $stat->downloads,
                        $stat->shares,
                    ]);
                }

                // Summary
                fputcsv($file, []);
                fputcsv($file, ['Summary']);
                fputcsv($file, ['Total Views', $dailyStats->sum('views')]);
                fputcsv($file, ['Total Generations', $dailyStats->sum('generations')]);
                fputcsv($file, ['Total Downloads', $dailyStats->sum('downloads')]);
                fputcsv($file, ['Total Shares', $dailyStats->sum('shares')]);

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time statistics (cached for 5 minutes).
     *
     * @param string $campaignId
     * @return JsonResponse
     */
    public function realtime(string $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);

            // Check authorization
            if (!$campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $cacheKey = "realtime_stats_{$campaignId}";

            $stats = Cache::remember($cacheKey, 300, function () use ($campaign) {
                $lastHour = now()->subHour();

                return [
                    'last_hour' => [
                        'views' => $campaign->views_count, // This would need timestamp tracking
                        'generations' => $campaign->userImages()->where('created_at', '>=', $lastHour)->count(),
                        'downloads' => $campaign->userImages()->where('updated_at', '>=', $lastHour)->sum('downloads_count'),
                    ],
                    'active_now' => [
                        'users_generating' => $campaign->userImages()->where('created_at', '>=', now()->subMinutes(5))->count(),
                    ],
                    'latest_activity' => $campaign->userImages()
                        ->with('user:id,name,country')
                        ->latest()
                        ->limit(10)
                        ->get()
                        ->map(function($image) {
                            return [
                                'user' => $image->user?->name ?? 'Guest',
                                'country' => $image->country,
                                'device' => $image->device_type,
                                'created_at' => $image->created_at->diffForHumans(),
                            ];
                        }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'cached_at' => now(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch realtime statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
