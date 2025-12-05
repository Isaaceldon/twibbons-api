<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CampaignController extends Controller
{
    /**
     * Display a listing of campaigns.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $campaigns = QueryBuilder::for(Campaign::class)
                ->allowedFilters([
                    'title',
                    'category',
                    'type',
                    'visibility',
                    AllowedFilter::exact('is_active'),
                    AllowedFilter::scope('public'),
                    AllowedFilter::scope('active'),
                    AllowedFilter::scope('published'),
                ])
                ->allowedSorts(['created_at', 'views_count', 'title', 'published_at'])
                ->allowedIncludes(['user', 'templates', 'statistics', 'primaryTemplate'])
                ->with('user:id,name,email,photo_url')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of user's campaigns.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myCampaigns(Request $request): JsonResponse
    {
        try {
            $campaigns = QueryBuilder::for(Campaign::class)
                ->where('user_id', $request->user()->id)
                ->allowedFilters(['category', 'type', 'visibility', 'is_active'])
                ->allowedSorts(['created_at', 'views_count', 'title'])
                ->allowedIncludes(['templates', 'statistics'])
                ->withCount(['templates', 'userImages'])
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created campaign.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category' => 'required|string|in:event,ngo,corporate,school,personal',
            'type' => 'required|string|in:image,video',
            'visibility' => 'required|string|in:public,private',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check campaign limit for free users
            $user = $request->user();
            if ($user->isUser() && $user->campaigns()->count() >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign limit reached. Upgrade to premium for unlimited campaigns.'
                ], 403);
            }

            $campaign = Campaign::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'slug' => Str::slug($request->title) . '-' . Str::random(6),
                'category' => $request->category,
                'type' => $request->type,
                'visibility' => $request->visibility,
                'is_active' => false, // Draft by default
            ]);

            // Create statistics record
            $campaign->statistics()->create([
                'total_views' => 0,
                'total_generations' => 0,
                'total_downloads' => 0,
                'total_shares' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'data' => [
                    'campaign' => $campaign->load('user:id,name,email')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified campaign.
     *
     * @param string $slug
     * @return JsonResponse
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $campaign = Campaign::where('slug', $slug)
                ->with([
                    'user:id,name,email,photo_url',
                    'templates' => function($query) {
                        $query->where('is_active', true);
                    },
                    'statistics',
                    'brandKits'
                ])
                ->firstOrFail();

            // Increment views
            $campaign->incrementViews();

            return response()->json([
                'success' => true,
                'data' => [
                    'campaign' => $campaign
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified campaign.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category' => 'nullable|string|in:event,ngo,corporate,school,personal',
            'visibility' => 'nullable|string|in:public,private',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = Campaign::findOrFail($id);

            // Check authorization
            if (!$campaign->isOwnedBy($request->user()) && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this campaign'
                ], 403);
            }

            // Update slug if title changed
            if ($request->has('title') && $request->title !== $campaign->title) {
                $campaign->slug = Str::slug($request->title) . '-' . Str::random(6);
            }

            $campaign->update($request->only(['title', 'description', 'category', 'visibility']));

            return response()->json([
                'success' => true,
                'message' => 'Campaign updated successfully',
                'data' => [
                    'campaign' => $campaign->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Publish a campaign.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($id);

            // Check authorization
            if (!$campaign->isOwnedBy($request->user()) && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to publish this campaign'
                ], 403);
            }

            // Check if campaign has at least one template
            if ($campaign->templates()->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign must have at least one template before publishing'
                ], 400);
            }

            $campaign->publish();

            // Generate QR code
            $campaign->generateQRCode();

            return response()->json([
                'success' => true,
                'message' => 'Campaign published successfully',
                'data' => [
                    'campaign' => $campaign->fresh(),
                    'public_url' => $campaign->getPublicUrl()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unpublish a campaign.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function unpublish(Request $request, string $id): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($id);

            // Check authorization
            if (!$campaign->isOwnedBy($request->user()) && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to unpublish this campaign'
                ], 403);
            }

            $campaign->unpublish();

            return response()->json([
                'success' => true,
                'message' => 'Campaign unpublished successfully',
                'data' => [
                    'campaign' => $campaign->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unpublish campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a campaign.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        try {
            $originalCampaign = Campaign::with('templates')->findOrFail($id);

            // Check authorization
            if (!$originalCampaign->isOwnedBy($request->user()) && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to duplicate this campaign'
                ], 403);
            }

            // Clone campaign
            $newCampaign = $originalCampaign->replicate();
            $newCampaign->title = $originalCampaign->title . ' (Copy)';
            $newCampaign->slug = Str::slug($newCampaign->title) . '-' . Str::random(6);
            $newCampaign->is_active = false;
            $newCampaign->published_at = null;
            $newCampaign->views_count = 0;
            $newCampaign->qr_code_path = null;
            $newCampaign->save();

            // Clone templates
            foreach ($originalCampaign->templates as $template) {
                $newTemplate = $template->replicate();
                $newTemplate->campaign_id = $newCampaign->id;
                $newTemplate->save();
            }

            // Create statistics
            $newCampaign->statistics()->create([
                'total_views' => 0,
                'total_generations' => 0,
                'total_downloads' => 0,
                'total_shares' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign duplicated successfully',
                'data' => [
                    'campaign' => $newCampaign->load('templates')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified campaign.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($id);

            // Check authorization
            if (!$campaign->isOwnedBy($request->user()) && !$request->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this campaign'
                ], 403);
            }

            $campaign->delete();

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign statistics.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function statistics(string $id): JsonResponse
    {
        try {
            $campaign = Campaign::with(['statistics', 'dailyStatistics' => function($query) {
                $query->orderBy('stats_date', 'desc')->limit(30);
            }])->findOrFail($id);

            $stats = [
                'overview' => [
                    'total_views' => $campaign->views_count,
                    'total_generations' => $campaign->getTotalGenerations(),
                    'total_downloads' => $campaign->getTotalDownloads(),
                    'total_shares' => $campaign->getTotalShares(),
                ],
                'demographics' => [
                    'countries' => $campaign->statistics->countries ?? [],
                    'devices' => $campaign->statistics->devices ?? [],
                ],
                'social' => [
                    'platforms' => $campaign->statistics->platforms ?? [],
                ],
                'daily_stats' => $campaign->dailyStatistics,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get popular campaigns.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function popular(Request $request): JsonResponse
    {
        try {
            $campaigns = Campaign::public()
                ->active()
                ->published()
                ->popular()
                ->with('user:id,name,email,photo_url')
                ->limit($request->get('limit', 10))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch popular campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent campaigns.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $campaigns = Campaign::public()
                ->active()
                ->published()
                ->recent($request->get('days', 7))
                ->with('user:id,name,email,photo_url')
                ->limit($request->get('limit', 10))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent campaigns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search campaigns.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->get('query');

            $campaigns = Campaign::public()
                ->active()
                ->published()
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->with('user:id,name,email,photo_url')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
