<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVideoJob;
use App\Models\VideoJob;
use App\Models\VideoTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VideoJobController extends Controller
{
    /**
     * Display a listing of video templates.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function templates(Request $request): JsonResponse
    {
        try {
            $templates = VideoTemplate::active()
                ->when($request->format, function($query, $format) {
                    $query->where('format', $format);
                })
                ->orderBy('usage_count', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $templates
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch video templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new video job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video_template_id' => 'required|exists:video_templates,id',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg|max:5120', // 5MB
            'text' => 'nullable|string|max:50',
            'parameters' => 'nullable|array',
            'parameters.text_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6})$/',
            'parameters.logo_position' => 'nullable|string|in:top-left,top-right,bottom-left,bottom-right,center',
            'parameters.animation_style' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if user can generate videos
        if (!$user->canGenerateVideo()) {
            return response()->json([
                'success' => false,
                'message' => 'Video generation is only available for premium users.',
                'data' => [
                    'upgrade_required' => true
                ]
            ], 403);
        }

        try {
            $template = VideoTemplate::findOrFail($request->video_template_id);

            // Upload logo if provided
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('video-logos', 's3');
            }

            // Validate at least logo or text is provided
            if (!$logoPath && !$request->text) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either logo or text must be provided'
                ], 422);
            }

            // Create video job
            $videoJob = VideoJob::create([
                'user_id' => $user->id,
                'video_template_id' => $template->id,
                'logo_path' => $logoPath,
                'text' => $request->text,
                'parameters' => $request->parameters ?? [
                    'text_color' => '#FFFFFF',
                    'logo_position' => 'center',
                ],
                'status' => 'pending',
                'progress' => 0,
            ]);

            // Increment template usage
            $template->increment('usage_count');

            // Dispatch job to queue
            ProcessVideoJob::dispatch($videoJob);

            return response()->json([
                'success' => true,
                'message' => 'Video generation started. Processing time: ~20 seconds.',
                'data' => [
                    'video_job' => [
                        'id' => $videoJob->id,
                        'status' => $videoJob->status,
                        'progress' => $videoJob->progress,
                        'template' => [
                            'name' => $template->name,
                            'duration' => $template->duration,
                            'format' => $template->format,
                        ],
                        'estimated_time' => 20, // seconds
                    ]
                ]
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create video job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get video job status.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function status(string $id): JsonResponse
    {
        try {
            $videoJob = VideoJob::with(['videoTemplate:id,name,format,duration'])
                ->findOrFail($id);

            // Check authorization
            if ($videoJob->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this video job'
                ], 403);
            }

            $data = [
                'id' => $videoJob->id,
                'status' => $videoJob->status,
                'progress' => $videoJob->progress,
                'output_path' => $videoJob->output_path,
                'output_url' => $videoJob->output_path ? Storage::disk('s3')->url($videoJob->output_path) : null,
                'processing_time' => $videoJob->processing_time,
                'error_message' => $videoJob->error_message,
                'template' => $videoJob->videoTemplate,
                'created_at' => $videoJob->created_at,
                'started_at' => $videoJob->started_at,
                'completed_at' => $videoJob->completed_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Video job not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * List user's video jobs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $videoJobs = VideoJob::where('user_id', $request->user()->id)
                ->with('videoTemplate:id,name,format,duration')
                ->when($request->status, function($query, $status) {
                    $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $videoJobs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch video jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending video jobs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $videoJobs = VideoJob::where('user_id', $request->user()->id)
                ->whereIn('status', ['pending', 'processing'])
                ->with('videoTemplate:id,name,format')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $videoJobs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completed video jobs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function completed(Request $request): JsonResponse
    {
        try {
            $videoJobs = VideoJob::where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->with('videoTemplate:id,name,format,duration')
                ->orderBy('completed_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $videoJobs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch completed jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download video.
     *
     * @param string $id
     * @return mixed
     */
    public function download(string $id)
    {
        try {
            $videoJob = VideoJob::findOrFail($id);

            // Check authorization
            if ($videoJob->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to download this video'
                ], 403);
            }

            // Check if video is completed
            if ($videoJob->status !== 'completed' || !$videoJob->output_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video is not ready for download'
                ], 400);
            }

            // Generate temporary signed URL for download
            $url = Storage::disk('s3')->temporaryUrl(
                $videoJob->output_path,
                now()->addMinutes(5)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $url,
                    'expires_in' => 300, // seconds
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
     * Retry failed video job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function retry(string $id): JsonResponse
    {
        try {
            $videoJob = VideoJob::findOrFail($id);

            // Check authorization
            if ($videoJob->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to retry this video job'
                ], 403);
            }

            // Only allow retry for failed jobs
            if ($videoJob->status !== 'failed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only retry failed video jobs'
                ], 400);
            }

            // Reset status
            $videoJob->update([
                'status' => 'pending',
                'progress' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            // Dispatch job again
            ProcessVideoJob::dispatch($videoJob);

            return response()->json([
                'success' => true,
                'message' => 'Video job retry started',
                'data' => [
                    'video_job' => $videoJob
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry video job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel pending video job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $videoJob = VideoJob::findOrFail($id);

            // Check authorization
            if ($videoJob->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to cancel this video job'
                ], 403);
            }

            // Only allow canceling pending jobs
            if ($videoJob->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only cancel pending video jobs'
                ], 400);
            }

            $videoJob->update([
                'status' => 'failed',
                'error_message' => 'Cancelled by user',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Video job cancelled successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel video job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete video job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $videoJob = VideoJob::findOrFail($id);

            // Check authorization
            if ($videoJob->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this video job'
                ], 403);
            }

            // Delete files from S3
            if ($videoJob->logo_path) {
                Storage::disk('s3')->delete($videoJob->logo_path);
            }
            if ($videoJob->output_path) {
                Storage::disk('s3')->delete($videoJob->output_path);
            }

            $videoJob->delete();

            return response()->json([
                'success' => true,
                'message' => 'Video job deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete video job',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get video job statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $stats = [
                'total_jobs' => $user->video_jobs()->count(),
                'completed' => $user->video_jobs()->where('status', 'completed')->count(),
                'pending' => $user->video_jobs()->where('status', 'pending')->count(),
                'processing' => $user->video_jobs()->where('status', 'processing')->count(),
                'failed' => $user->video_jobs()->where('status', 'failed')->count(),
                'average_processing_time' => $user->video_jobs()
                    ->where('status', 'completed')
                    ->avg('processing_time'),
                'this_month' => $user->video_jobs()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch video job statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
