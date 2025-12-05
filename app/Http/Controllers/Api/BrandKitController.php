<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrandKit;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ColorThief\ColorThief;

class BrandKitController extends Controller
{
    /**
     * Display a listing of user's brand kits.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $brandKits = BrandKit::where('user_id', $request->user()->id)
                ->withCount('campaigns')
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $brandKits
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch brand kits',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created brand kit.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'logo' => 'required|file|mimes:png,jpg,jpeg,svg|max:5120', // 5MB
            'extract_colors' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Upload logo
            $logoPath = $request->file('logo')->store('brand-kits/logos', 's3');
            $logoUrl = Storage::disk('s3')->url($logoPath);

            // Extract colors from logo
            $colorPalette = [];
            if ($request->extract_colors !== false) {
                try {
                    // Download logo temporarily for color extraction
                    $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.png';
                    file_put_contents($tempPath, file_get_contents($logoUrl));

                    // Extract dominant colors
                    $palette = ColorThief::getPalette($tempPath, 4, 10);

                    foreach ($palette as $rgb) {
                        $colorPalette[] = sprintf("#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2]);
                    }

                    // Clean up
                    unlink($tempPath);

                } catch (\Exception $e) {
                    // If color extraction fails, continue with empty palette
                    \Log::warning('Color extraction failed: ' . $e->getMessage());
                }
            }

            // Create brand kit
            $brandKit = BrandKit::create([
                'user_id' => $user->id,
                'name' => $request->name,
                'logo_path' => $logoPath,
                'color_palette' => $colorPalette,
                'typography' => $this->generateTypography($colorPalette),
                'patterns' => [],
                'metadata' => [
                    'logo_url' => $logoUrl,
                ],
                'is_default' => $user->brand_kits()->count() === 0, // First one is default
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Brand kit created successfully',
                'data' => [
                    'brand_kit' => $brandKit
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create brand kit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified brand kit.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $brandKit = BrandKit::with(['campaigns' => function($query) {
                $query->select('campaigns.id', 'title', 'slug', 'created_at')
                      ->withPivot('applied_settings');
            }])->findOrFail($id);

            // Check authorization
            if ($brandKit->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this brand kit'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'brand_kit' => $brandKit
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Brand kit not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified brand kit.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'color_palette' => 'nullable|array|max:6',
            'color_palette.*' => 'string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'typography' => 'nullable|array',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $brandKit = BrandKit::findOrFail($id);

            // Check authorization
            if ($brandKit->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this brand kit'
                ], 403);
            }

            // Update logo if provided
            if ($request->hasFile('logo')) {
                // Delete old logo
                if ($brandKit->logo_path) {
                    Storage::disk('s3')->delete($brandKit->logo_path);
                }

                $logoPath = $request->file('logo')->store('brand-kits/logos', 's3');
                $brandKit->logo_path = $logoPath;
            }

            // Update other fields
            if ($request->has('name')) {
                $brandKit->name = $request->name;
            }

            if ($request->has('color_palette')) {
                $brandKit->color_palette = $request->color_palette;
            }

            if ($request->has('typography')) {
                $brandKit->typography = $request->typography;
            }

            $brandKit->save();

            return response()->json([
                'success' => true,
                'message' => 'Brand kit updated successfully',
                'data' => [
                    'brand_kit' => $brandKit->fresh()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update brand kit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set brand kit as default.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function setDefault(string $id): JsonResponse
    {
        try {
            $brandKit = BrandKit::findOrFail($id);

            // Check authorization
            if ($brandKit->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Remove default from other brand kits
            BrandKit::where('user_id', auth()->id())
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            // Set this as default
            $brandKit->update(['is_default' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Default brand kit updated successfully',
                'data' => [
                    'brand_kit' => $brandKit
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default brand kit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply brand kit to campaign.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function applyToCampaign(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign_id' => 'required|exists:campaigns,id',
            'generate_templates' => 'nullable|boolean',
            'template_count' => 'nullable|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $brandKit = BrandKit::findOrFail($id);
            $campaign = Campaign::findOrFail($request->campaign_id);

            // Check authorization
            if ($brandKit->user_id !== auth()->id() || $campaign->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Attach brand kit to campaign
            $campaign->brandKits()->syncWithoutDetaching([
                $brandKit->id => [
                    'applied_settings' => [
                        'colors' => $brandKit->color_palette,
                        'typography' => $brandKit->typography,
                        'applied_at' => now(),
                    ]
                ]
            ]);

            // Optionally generate templates
            $templates = [];
            if ($request->generate_templates) {
                $count = $request->template_count ?? 3;
                // Here you would call your AI service to generate templates
                // For now, we'll just return success
                // $templates = $this->generateTemplatesFromBrandKit($brandKit, $campaign, $count);
            }

            return response()->json([
                'success' => true,
                'message' => 'Brand kit applied to campaign successfully',
                'data' => [
                    'brand_kit' => $brandKit,
                    'campaign' => $campaign->fresh(['brandKits']),
                    'templates_generated' => count($templates),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply brand kit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified brand kit.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $brandKit = BrandKit::findOrFail($id);

            // Check authorization
            if ($brandKit->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this brand kit'
                ], 403);
            }

            // Prevent deletion if it's the only brand kit and is default
            if ($brandKit->is_default && BrandKit::where('user_id', auth()->id())->count() === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the only default brand kit'
                ], 400);
            }

            // Delete logo file
            if ($brandKit->logo_path) {
                Storage::disk('s3')->delete($brandKit->logo_path);
            }

            $brandKit->delete();

            // If deleted brand kit was default, set another as default
            if ($brandKit->is_default) {
                BrandKit::where('user_id', auth()->id())
                    ->first()
                    ?->update(['is_default' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Brand kit deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete brand kit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate harmonized color palette.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generatePalette(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'base_color' => 'required|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'scheme' => 'nullable|string|in:monochromatic,analogous,complementary,triadic',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $baseColor = $request->base_color;
            $scheme = $request->scheme ?? 'analogous';

            $palette = $this->generateColorScheme($baseColor, $scheme);

            return response()->json([
                'success' => true,
                'data' => [
                    'palette' => $palette,
                    'scheme' => $scheme,
                    'base_color' => $baseColor,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate palette',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate brand kit.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function duplicate(string $id): JsonResponse
    {
        try {
            $originalBrandKit = BrandKit::findOrFail($id);

            // Check authorization
            if ($originalBrandKit->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to duplicate this brand kit'
                ], 403);
            }

            // Clone brand kit
            $newBrandKit = $originalBrandKit->replicate();
            $newBrandKit->name = $originalBrandKit->name . ' (Copy)';
            $newBrandKit->is_default = false;

            // Copy logo file
            if ($originalBrandKit->logo_path) {
                $newLogoPath = str_replace(
                    basename($originalBrandKit->logo_path),
                    uniqid() . '_' . basename($originalBrandKit->logo_path),
                    $originalBrandKit->logo_path
                );

                Storage::disk('s3')->copy($originalBrandKit->logo_path, $newLogoPath);
                $newBrandKit->logo_path = $newLogoPath;
            }

            $newBrandKit->save();

            return response()->json([
                'success' => true,
                'message' => 'Brand kit duplicated successfully',
                'data' => [
                    'brand_kit' => $newBrandKit
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate brand kit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Generate typography suggestions.
     *
     * @param array $colorPalette
     * @return array
     */
    private function generateTypography(array $colorPalette): array
    {
        return [
            'primary_font' => 'Inter',
            'secondary_font' => 'Roboto',
            'heading_color' => $colorPalette[0] ?? '#000000',
            'body_color' => $colorPalette[1] ?? '#333333',
            'accent_color' => $colorPalette[2] ?? '#666666',
        ];
    }

    /**
     * Helper: Generate color scheme.
     *
     * @param string $baseColor
     * @param string $scheme
     * @return array
     */
    private function generateColorScheme(string $baseColor, string $scheme): array
    {
        // Convert hex to HSL
        list($h, $s, $l) = $this->hexToHsl($baseColor);

        $palette = [$baseColor];

        switch ($scheme) {
            case 'monochromatic':
                $palette[] = $this->hslToHex($h, $s, max(0, $l - 20));
                $palette[] = $this->hslToHex($h, $s, min(100, $l + 20));
                $palette[] = $this->hslToHex($h, max(0, $s - 20), $l);
                break;

            case 'analogous':
                $palette[] = $this->hslToHex(($h + 30) % 360, $s, $l);
                $palette[] = $this->hslToHex(($h - 30 + 360) % 360, $s, $l);
                $palette[] = $this->hslToHex(($h + 60) % 360, $s, $l);
                break;

            case 'complementary':
                $palette[] = $this->hslToHex(($h + 180) % 360, $s, $l);
                $palette[] = $this->hslToHex($h, $s, min(100, $l + 20));
                $palette[] = $this->hslToHex(($h + 180) % 360, $s, min(100, $l + 20));
                break;

            case 'triadic':
                $palette[] = $this->hslToHex(($h + 120) % 360, $s, $l);
                $palette[] = $this->hslToHex(($h + 240) % 360, $s, $l);
                $palette[] = $this->hslToHex($h, $s, min(100, $l + 15));
                break;
        }

        return array_unique($palette);
    }

    /**
     * Helper: Convert hex to HSL.
     */
    private function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max == $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)) / 6; break;
                case $g: $h = (($b - $r) / $d + 2) / 6; break;
                case $b: $h = (($r - $g) / $d + 4) / 6; break;
            }
        }

        return [round($h * 360), round($s * 100), round($l * 100)];
    }

    /**
     * Helper: Convert HSL to hex.
     */
    private function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->hueToRgb($p, $q, $h + 1/3);
            $g = $this->hueToRgb($p, $q, $h);
            $b = $this->hueToRgb($p, $q, $h - 1/3);
        }

        return sprintf("#%02x%02x%02x", round($r * 255), round($g * 255), round($b * 255));
    }

    /**
     * Helper: Hue to RGB conversion.
     */
    private function hueToRgb($p, $q, $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }
}
