<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAIFrameJob;
use App\Models\AiGeneration;
use App\Models\AiProvider;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AIGenerationController extends Controller
{
    /**
     * Generate AI frames from prompt.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string|min:10|max:1000',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg|max:5120', // 5MB
            'parameters' => 'nullable|array',
            'parameters.style' => 'nullable|string',
            'parameters.colors' => 'nullable|array',
            'parameters.size' => 'nullable|string|in:1024x1024,1024x1536,1536x1024',
            'parameters.count' => 'nullable|integer|min:1|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if user can generate AI
        if (!$user->canGenerateAI()) {
            $remaining = $user->getRemainingAIGenerations();

            return response()->json([
                'success' => false,
                'message' => 'AI generation limit reached for this month.',
                'data' => [
                    'remaining_generations' => $remaining,
                    'upgrade_required' => true
                ]
            ], 403);
        }

        try {
            // Get active AI provider
            $provider = AiProvider::active()
                ->byPriority()
                ->where(function($query) {
                    $query->whereRaw('last_used_at IS NULL')
                          ->orWhere('last_used_at', '<=', now()->subMinutes(5));
                })
                ->first();

            if (!$provider || !$provider->canProcess()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No AI provider available at the moment. Please try again later.'
                ], 503);
            }

            // Handle logo upload
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('ai-logos', 's3');
            }

            // Verify campaign ownership if provided
            if ($request->campaign_id) {
                $campaign = Campaign::findOrFail($request->campaign_id);

                if (!$campaign->isOwnedBy($user) && !$user->isAdmin()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to add templates to this campaign'
                    ], 403);
                }
            }

            // Create AI generation record
            $generation = AiGeneration::create([
                'user_id' => $user->id,
                'campaign_id' => $request->campaign_id,
                'ai_provider_id' => $provider->id,
                'prompt' => $request->prompt,
                'logo_path' => $logoPath,
                'parameters' => $request->parameters ?? [
                    'size' => '1024x1024',
                    'count' => 4,
                ],
                'status' => 'pending',
                'attempts' => 0,
            ]);

            // Dispatch job to queue
            GenerateAIFrameJob::dispatch($generation);

            return response()->json([
                'success' => true,
                'message' => 'AI generation started. You will be notified when it\'s ready.',
                'data' => [
                    'generation' => [
                        'id' => $generation->id,
                        'status' => $generation->status,
                        'prompt' => $generation->prompt,
                        'estimated_time' => 10, // seconds
                    ],
                    'remaining_generations' => $user->getRemainingAIGenerations(),
                ]
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start AI generation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get generation status.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function status(string $id): JsonResponse
    {
        try {
            $generation = AiGeneration::with(['aiProvider:id,name', 'campaign:id,title'])
                ->findOrFail($id);

            // Check authorization
            if ($generation->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this generation'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'generation' => [
                        'id' => $generation->id,
                        'status' => $generation->status,
                        'prompt' => $generation->prompt,
                        'results' => $generation->results,
                        'generation_time' => $generation->generation_time,
                        'error_message' => $generation->error_message,
                        'provider' => $generation->aiProvider->name ?? null,
                        'created_at' => $generation->created_at,
                        'completed_at' => $generation->completed_at,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * List user's AI generations.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $generations = AiGeneration::where('user_id', $request->user()->id)
                ->with(['aiProvider:id,name', 'campaign:id,title,slug'])
                ->when($request->status, function($query, $status) {
                    $query->where('status', $status);
                })
                ->when($request->campaign_id, function($query, $campaignId) {
                    $query->where('campaign_id', $campaignId);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $generations
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch generations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed generation.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function retry(string $id): JsonResponse
    {
        try {
            $generation = AiGeneration::findOrFail($id);

            // Check authorization
            if ($generation->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to retry this generation'
                ], 403);
            }

            // Only allow retry for failed generations
            if ($generation->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only retry failed generations'
                ], 400);
            }

            // Check retry limit
            if ($generation->attempts >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum retry attempts reached'
                ], 400);
            }

            // Reset status
            $generation->update([
                'status' => 'pending',
                'error_message' => null,
                'attempts' => $generation->attempts + 1,
            ]);

            // Dispatch job again
            GenerateAIFrameJob::dispatch($generation);

            return response()->json([
                'success' => true,
                'message' => 'Generation retry started',
                'data' => [
                    'generation' => $generation
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry generation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Select and save AI generated result as campaign template.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function selectResult(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'result_index' => 'required|integer|min:0',
            'campaign_id' => 'required|exists:campaigns,id',
            'set_as_primary' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $generation = AiGeneration::findOrFail($id);

            // Check authorization
            if ($generation->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Verify generation is completed
            if ($generation->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Generation not completed yet'
                ], 400);
            }

            // Verify result index exists
            $results = $generation->results;
            if (!isset($results[$request->result_index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid result index'
                ], 400);
            }

            // Get campaign and verify ownership
            $campaign = Campaign::findOrFail($request->campaign_id);
            if (!$campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to add template to this campaign'
                ], 403);
            }

            $selectedResult = $results[$request->result_index];

            // Create campaign template
            $template = $campaign->templates()->create([
                'ai_generation_id' => $generation->id,
                'type' => 'image',
                'file_path' => $selectedResult['url'],
                'thumbnail_path' => $selectedResult['thumbnail'] ?? null,
                'metadata' => [
                    'prompt' => $generation->prompt,
                    'provider' => $generation->aiProvider->name,
                    'generation_time' => $generation->generation_time,
                ],
                'width' => 1024,
                'height' => 1024,
                'is_primary' => $request->set_as_primary ?? false,
                'is_active' => true,
            ]);

            // Set as primary if requested
            if ($request->set_as_primary) {
                $template->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Template added to campaign successfully',
                'data' => [
                    'template' => $template->load('campaign')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete AI generation.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $generation = AiGeneration::findOrFail($id);

            // Check authorization
            if ($generation->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this generation'
                ], 403);
            }

            // Delete logo file if exists
            if ($generation->logo_path) {
                Storage::disk('s3')->delete($generation->logo_path);
            }

            $generation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Generation deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete generation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's AI generation stats.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = [
                'total_generations' => $user->getAIGenerationsCount(),
                'this_month' => $user->getAIGenerationsThisMonth(),
                'remaining' => $user->getRemainingAIGenerations(),
                'by_status' => [
                    'completed' => $user->ai_generations()->where('status', 'completed')->count(),
                    'failed' => $user->ai_generations()->where('status', 'failed')->count(),
                    'pending' => $user->ai_generations()->whereIn('status', ['pending', 'processing'])->count(),
                ],
                'average_generation_time' => $user->ai_generations()
                    ->where('status', 'completed')
                    ->avg('generation_time'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
