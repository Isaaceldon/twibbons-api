<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;

class CampaignTemplateController extends Controller
{
    /**
     * Upload and create a new campaign template.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:campaigns,id',
            'file' => 'required|file|mimes:png,svg,jpg,jpeg|max:10240', // 10MB
            'type' => 'nullable|string|in:image,video',
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
            $campaign = Campaign::findOrFail($request->campaign_id);

            // Check authorization
            if (!$campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to add templates to this campaign'
                ], 403);
            }

            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $type = $request->type ?? 'image';

            // Process and upload file
            $fileInfo = $this->processAndUploadFile($file, $campaign->id);

            // Create template record
            $template = CampaignTemplate::create([
                'campaign_id' => $campaign->id,
                'type' => $type,
                'file_path' => $fileInfo['file_path'],
                'thumbnail_path' => $fileInfo['thumbnail_path'] ?? null,
                'metadata' => [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                ],
                'width' => $fileInfo['width'] ?? 1080,
                'height' => $fileInfo['height'] ?? 1080,
                'file_size' => $file->getSize(),
                'is_primary' => $request->set_as_primary ?? false,
                'is_active' => true,
            ]);

            // Set as primary if requested
            if ($request->set_as_primary) {
                $template->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Template uploaded successfully',
                'data' => [
                    'template' => $template->load('campaign:id,title,slug')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update template metadata.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template = CampaignTemplate::with('campaign')->findOrFail($id);

            // Check authorization
            if (!$template->campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this template'
                ], 403);
            }

            $template->update($request->only(['is_active', 'metadata']));

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => [
                    'template' => $template->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set template as primary.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function setPrimary(string $id): JsonResponse
    {
        try {
            $template = CampaignTemplate::with('campaign')->findOrFail($id);

            // Check authorization
            if (!$template->campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $template->makePrimary();

            return response()->json([
                'success' => true,
                'message' => 'Template set as primary successfully',
                'data' => [
                    'template' => $template->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set template as primary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a template.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function duplicate(string $id): JsonResponse
    {
        try {
            $template = CampaignTemplate::with('campaign')->findOrFail($id);

            // Check authorization
            if (!$template->campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to duplicate this template'
                ], 403);
            }

            // Duplicate file on S3
            $newFilePath = $this->duplicateS3File($template->file_path);
            $newThumbnailPath = $template->thumbnail_path
                ? $this->duplicateS3File($template->thumbnail_path)
                : null;

            // Create duplicate record
            $newTemplate = $template->replicate();
            $newTemplate->file_path = $newFilePath;
            $newTemplate->thumbnail_path = $newThumbnailPath;
            $newTemplate->is_primary = false;
            $newTemplate->save();

            return response()->json([
                'success' => true,
                'message' => 'Template duplicated successfully',
                'data' => [
                    'template' => $newTemplate
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $template = CampaignTemplate::with('campaign')->findOrFail($id);

            // Check authorization
            if (!$template->campaign->isOwnedBy(auth()->user()) && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this template'
                ], 403);
            }

            // Prevent deletion if it's the only template and campaign is published
            if ($template->campaign->templates()->count() === 1 && $template->campaign->isPublished()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the only template of a published campaign'
                ], 400);
            }

            // Delete files from S3
            if ($template->file_path) {
                Storage::disk('s3')->delete($template->file_path);
            }
            if ($template->thumbnail_path) {
                Storage::disk('s3')->delete($template->thumbnail_path);
            }

            $template->delete();

            // If deleted template was primary, set another as primary
            if ($template->is_primary) {
                $newPrimary = $template->campaign->templates()->first();
                $newPrimary?->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process and upload file to S3.
     *
     * @param $file
     * @param string $campaignId
     * @return array
     */
    protected function processAndUploadFile($file, string $campaignId): array
    {
        $extension = $file->getClientOriginalExtension();
        $filename = uniqid() . '.' . $extension;
        $path = "campaign-templates/{$campaignId}/{$filename}";

        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
            // Process image
            $image = Image::make($file);

            // Get dimensions
            $width = $image->width();
            $height = $image->height();

            // Resize if too large (max 2048x2048)
            if ($width > 2048 || $height > 2048) {
                $image->resize(2048, 2048, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            // Ensure PNG format for transparency
            if ($extension !== 'png') {
                $extension = 'png';
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '.png';
                $path = "campaign-templates/{$campaignId}/{$filename}";
            }

            // Save optimized image to temp
            $tempPath = sys_get_temp_dir() . '/' . $filename;
            $image->save($tempPath, 90, 'png');

            // Upload to S3
            Storage::disk('s3')->put($path, file_get_contents($tempPath));

            // Generate thumbnail
            $thumbnail = Image::make($tempPath)->fit(300, 300);
            $thumbnailPath = "campaign-templates/{$campaignId}/thumbnails/{$filename}";
            Storage::disk('s3')->put($thumbnailPath, $thumbnail->encode('png'));

            // Clean up
            unlink($tempPath);

            return [
                'file_path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'width' => $image->width(),
                'height' => $image->height(),
            ];

        } elseif ($extension === 'svg') {
            // Upload SVG directly
            $content = file_get_contents($file->getRealPath());
            Storage::disk('s3')->put($path, $content);

            return [
                'file_path' => $path,
                'width' => 1080, // Default for SVG
                'height' => 1080,
            ];
        }

        throw new \Exception('Unsupported file type');
    }

    /**
     * Duplicate file on S3.
     *
     * @param string $originalPath
     * @return string
     */
    protected function duplicateS3File(string $originalPath): string
    {
        $pathInfo = pathinfo($originalPath);
        $newFilename = uniqid() . '.' . $pathInfo['extension'];
        $newPath = $pathInfo['dirname'] . '/' . $newFilename;

        Storage::disk('s3')->copy($originalPath, $newPath);

        return $newPath;
    }
}
