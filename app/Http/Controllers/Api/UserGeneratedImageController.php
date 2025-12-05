<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTwibbonJob;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\Share;
use App\Models\UserGeneratedImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserGeneratedImageController extends Controller
{
    /**
     * Generate a twibbon (combine user photo with campaign frame).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:campaigns,id',
            'template_id' => 'required|exists:campaign_templates,id',
            'photo' => 'required|file|mimes:jpg,jpeg,png|max:10240', // 10MB
            'customizations' => 'nullable|array',
            'customizations.text' => 'nullable|string|max:100',
            'customizations.text_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6})$/',
            'customizations.text_position' => 'nullable|array',
            'customizations.stickers' => 'nullable|array',
            'customizations.photo_position' => 'nullable|array',
            'customizations.photo_scale' => 'nullable|numeric|min:0.1|max:3',
            'customizations.photo_rotation' => 'nullable|numeric|min:-180|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $campaign = Campaign::findOrFail($request->campaign_id);
            $template = CampaignTemplate::findOrFail($request->template_id);

            // Verify template belongs to campaign
            if ($template->campaign_id !== $campaign->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template does not belong to this campaign'
                ], 400);
            }

            // Verify campaign is active and published
            if (!$campaign->is_active || !$campaign->isPublished()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign is not available'
                ], 400);
            }

            // Upload user photo
            $photoPath = $request->file('photo')->store('user-photos', 's3');

            // Get user info (if authenticated)
            $userId = auth()->check() ? auth()->id() : null;

            // Get device and location info
            $deviceType = $this->detectDeviceType($request);
            $ipAddress = $request->ip();
            $country = $this->getCountryFromIp($ipAddress);

            // Create record
            $userImage = UserGeneratedImage::create([
                'user_id' => $userId,
                'campaign_id' => $campaign->id,
                'campaign_template_id' => $template->id,
                'photo_path' => $photoPath,
                'result_path' => null, // Will be set after processing
                'customizations' => $request->customizations ?? [],
                'device_type' => $deviceType,
                'ip_address' => $ipAddress,
                'country' => $country,
                'user_agent' => $request->userAgent(),
                'downloads_count' => 0,
            ]);

            // Dispatch job to generate twibbon
            GenerateTwibbonJob::dispatch($userImage);

            // Update campaign statistics
            $campaign->statistics?->incrementGenerations();
            $campaign->statistics?->trackCountry($country);

            return response()->json([
                'success' => true,
                'message' => 'Twibbon generation started. Processing...',
                'data' => [
                    'user_image' => [
                        'id' => $userImage->id,
                        'campaign' => [
                            'id' => $campaign->id,
                            'title' => $campaign->title,
                            'slug' => $campaign->slug,
                        ],
                        'estimated_time' => 5, // seconds
                    ]
                ]
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate twibbon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get generated twibbon status and result.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $userImage = UserGeneratedImage::with([
                'campaign:id,title,slug',
                'campaignTemplate:id,type,file_path'
            ])->findOrFail($id);

            $data = [
                'id' => $userImage->id,
                'photo_url' => Storage::disk('s3')->url($userImage->photo_path),
                'result_url' => $userImage->result_path ? Storage::disk('s3')->url($userImage->result_path) : null,
                'customizations' => $userImage->customizations,
                'campaign' => $userImage->campaign,
                'downloads_count' => $userImage->downloads_count,
                'created_at' => $userImage->created_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Twibbon not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Download generated twibbon.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function download(string $id): JsonResponse
    {
        try {
            $userImage = UserGeneratedImage::findOrFail($id);

            if (!$userImage->result_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twibbon is not ready for download yet'
                ], 400);
            }

            // Increment download count
            $userImage->increment('downloads_count');

            // Update campaign statistics
            $userImage->campaign->statistics?->incrementDownloads();

            // Generate temporary signed URL
            $url = Storage::disk('s3')->temporaryUrl(
                $userImage->result_path,
                now()->addMinutes(5)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $url,
                    'expires_in' => 300, // seconds
                    'filename' => basename($userImage->result_path),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate download link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Share twibbon on social media.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function share(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:facebook,twitter,instagram,linkedin,whatsapp',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userImage = UserGeneratedImage::with('campaign')->findOrFail($id);

            if (!$userImage->result_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twibbon is not ready for sharing yet'
                ], 400);
            }

            $platform = $request->platform;
            $imageUrl = Storage::disk('s3')->url($userImage->result_path);
            $campaignUrl = $userImage->campaign->getPublicUrl();

            // Generate share URLs based on platform
            $shareUrl = $this->generateShareUrl($platform, $imageUrl, $campaignUrl, $userImage->campaign->title);

            // Track share
            Share::create([
                'user_generated_image_id' => $userImage->id,
                'platform' => $platform,
                'share_url' => $shareUrl,
                'metadata' => [
                    'campaign_title' => $userImage->campaign->title,
                ],
                'ip_address' => $request->ip(),
                'country' => $this->getCountryFromIp($request->ip()),
            ]);

            // Update campaign statistics
            $userImage->campaign->statistics?->incrementShares($platform);

            return response()->json([
                'success' => true,
                'message' => 'Share link generated successfully',
                'data' => [
                    'share_url' => $shareUrl,
                    'platform' => $platform,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate share link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List user's generated twibbons.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function myTwibbons(Request $request): JsonResponse
    {
        try {
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $twibbons = UserGeneratedImage::where('user_id', auth()->id())
                ->with(['campaign:id,title,slug', 'campaignTemplate:id,type'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $twibbons
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch twibbons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user generated image.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $userImage = UserGeneratedImage::findOrFail($id);

            // Check authorization
            if (auth()->check() && $userImage->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this twibbon'
                ], 403);
            }

            // Delete files from S3
            if ($userImage->photo_path) {
                Storage::disk('s3')->delete($userImage->photo_path);
            }
            if ($userImage->result_path) {
                Storage::disk('s3')->delete($userImage->result_path);
            }

            $userImage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Twibbon deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete twibbon',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get twibbon generation statistics for a campaign.
     *
     * @param string $campaignId
     * @return JsonResponse
     */
    public function campaignStats(string $campaignId): JsonResponse
    {
        try {
            $campaign = Campaign::findOrFail($campaignId);

            $stats = [
                'total_generations' => $campaign->userImages()->count(),
                'total_downloads' => $campaign->userImages()->sum('downloads_count'),
                'unique_users' => $campaign->userImages()->whereNotNull('user_id')->distinct('user_id')->count(),
                'by_device' => $campaign->userImages()
                    ->selectRaw('device_type, count(*) as count')
                    ->groupBy('device_type')
                    ->pluck('count', 'device_type'),
                'by_country' => $campaign->userImages()
                    ->selectRaw('country, count(*) as count')
                    ->groupBy('country')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->pluck('count', 'country'),
                'recent_generations' => $campaign->userImages()
                    ->whereDate('created_at', '>=', now()->subDays(7))
                    ->count(),
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
     * Helper: Detect device type from user agent.
     *
     * @param Request $request
     * @return string
     */
    private function detectDeviceType(Request $request): string
    {
        $userAgent = $request->userAgent();

        if (preg_match('/mobile/i', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/tablet/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Helper: Get country from IP address.
     *
     * @param string $ip
     * @return string|null
     */
    private function getCountryFromIp(string $ip): ?string
    {
        // In production, use a geolocation service like MaxMind GeoIP2
        // For now, return null or implement a basic lookup

        try {
            // Example using ip-api.com (free tier)
            $response = file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode");
            $data = json_decode($response, true);
            return $data['countryCode'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper: Generate share URL for platform.
     *
     * @param string $platform
     * @param string $imageUrl
     * @param string $campaignUrl
     * @param string $title
     * @return string
     */
    private function generateShareUrl(string $platform, string $imageUrl, string $campaignUrl, string $title): string
    {
        $text = urlencode("Check out my twibbon for: {$title}");
        $url = urlencode($campaignUrl);
        $imageEncoded = urlencode($imageUrl);

        return match($platform) {
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
            'twitter' => "https://twitter.com/intent/tweet?text={$text}&url={$url}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
            'whatsapp' => "https://wa.me/?text={$text}%20{$url}",
            'instagram' => $imageUrl, // Instagram doesn't support direct sharing, return image URL
            default => $campaignUrl,
        };
    }
}
