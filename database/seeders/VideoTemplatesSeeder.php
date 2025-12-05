<?php


namespace Database\Seeders;

use App\Models\VideoTemplate;
use Illuminate\Database\Seeder;

class VideoTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Modern Fade',
                'description' => 'Modern fade-in animation with smooth transitions',
                'video_path' => 'video-templates/modern-fade.mp4',
                'duration' => 10,
                'format' => '1:1',
                'width' => 1080,
                'height' => 1080,
                'overlay_zones' => [
                    'logo' => ['x' => 540, 'y' => 540, 'size' => 200],
                    'text' => ['x' => 540, 'y' => 950, 'max_width' => 800],
                ],
                'thumbnail_path' => 'video-templates/thumbnails/modern-fade.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Dynamic Spin',
                'description' => 'Dynamic spinning animation with 3D effects',
                'video_path' => 'video-templates/dynamic-spin.mp4',
                'duration' => 8,
                'format' => '1:1',
                'width' => 1080,
                'height' => 1080,
                'overlay_zones' => [
                    'logo' => ['x' => 540, 'y' => 540, 'size' => 200],
                ],
                'thumbnail_path' => 'video-templates/thumbnails/dynamic-spin.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Story Format',
                'description' => 'Vertical format optimized for Instagram Stories and Reels',
                'video_path' => 'video-templates/story-format.mp4',
                'duration' => 12,
                'format' => '9:16',
                'width' => 1080,
                'height' => 1920,
                'overlay_zones' => [
                    'logo' => ['x' => 540, 'y' => 960, 'size' => 300],
                    'text' => ['x' => 540, 'y' => 1700, 'max_width' => 900],
                ],
                'thumbnail_path' => 'video-templates/thumbnails/story-format.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            VideoTemplate::create($template);
        }
    }
}
