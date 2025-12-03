<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CampaignTemplate
 * 
 * @property string $id
 * @property string $campaign_id
 * @property string|null $ai_generation_id
 * @property string $type
 * @property string $file_path
 * @property string|null $thumbnail_path
 * @property string|null $metadata
 * @property int $width
 * @property int $height
 * @property int $file_size
 * @property bool $is_primary
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property AiGeneration|null $ai_generation
 * @property Campaign $campaign
 * @property Collection|UserGeneratedImage[] $user_generated_images
 *
 * @package App\Models
 */
class CampaignTemplate extends Model
{
	protected $table = 'campaign_templates';
	public $incrementing = false;

	protected $casts = [
		'width' => 'int',
		'height' => 'int',
		'file_size' => 'int',
		'is_primary' => 'bool',
		'is_active' => 'bool'
	];

	protected $fillable = [
		'campaign_id',
		'ai_generation_id',
		'type',
		'file_path',
		'thumbnail_path',
		'metadata',
		'width',
		'height',
		'file_size',
		'is_primary',
		'is_active'
	];

	public function ai_generation()
	{
		return $this->belongsTo(AiGeneration::class);
	}

	public function campaign()
	{
		return $this->belongsTo(Campaign::class);
	}

	public function user_generated_images()
	{
		return $this->hasMany(UserGeneratedImage::class);
	}
}
