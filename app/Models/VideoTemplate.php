<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class VideoTemplate
 * 
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $video_path
 * @property int $duration
 * @property string $format
 * @property int $width
 * @property int $height
 * @property string|null $overlay_zones
 * @property string|null $thumbnail_path
 * @property bool $is_active
 * @property int $usage_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|VideoJob[] $video_jobs
 *
 * @package App\Models
 */
class VideoTemplate extends Model
{
	protected $table = 'video_templates';
	public $incrementing = false;

	protected $casts = [
		'duration' => 'int',
		'width' => 'int',
		'height' => 'int',
		'is_active' => 'bool',
		'usage_count' => 'int'
	];

	protected $fillable = [
		'name',
		'description',
		'video_path',
		'duration',
		'format',
		'width',
		'height',
		'overlay_zones',
		'thumbnail_path',
		'is_active',
		'usage_count'
	];

	public function video_jobs()
	{
		return $this->hasMany(VideoJob::class);
	}
}
