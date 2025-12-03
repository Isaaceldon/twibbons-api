<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Campaign
 * 
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string|null $description
 * @property string $slug
 * @property string $category
 * @property string $type
 * @property string $visibility
 * @property string|null $qr_code_path
 * @property bool $is_active
 * @property int $views_count
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property User $user
 * @property Collection|AiGeneration[] $ai_generations
 * @property Collection|BrandKit[] $brand_kits
 * @property CampaignStatistic|null $campaign_statistic
 * @property Collection|CampaignTemplate[] $campaign_templates
 * @property Collection|StatisticsDaily[] $statistics_dailies
 * @property Collection|UserGeneratedImage[] $user_generated_images
 *
 * @package App\Models
 */
class Campaign extends Model
{
	use SoftDeletes;
	protected $table = 'campaigns';
	public $incrementing = false;

	protected $casts = [
		'is_active' => 'bool',
		'views_count' => 'int',
		'published_at' => 'datetime'
	];

	protected $fillable = [
		'user_id',
		'title',
		'description',
		'slug',
		'category',
		'type',
		'visibility',
		'qr_code_path',
		'is_active',
		'views_count',
		'published_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function ai_generations()
	{
		return $this->hasMany(AiGeneration::class);
	}

	public function brand_kits()
	{
		return $this->belongsToMany(BrandKit::class)
					->withPivot('id', 'applied_settings')
					->withTimestamps();
	}

	public function campaign_statistic()
	{
		return $this->hasOne(CampaignStatistic::class);
	}

	public function campaign_templates()
	{
		return $this->hasMany(CampaignTemplate::class);
	}

	public function statistics_dailies()
	{
		return $this->hasMany(StatisticsDaily::class);
	}

	public function user_generated_images()
	{
		return $this->hasMany(UserGeneratedImage::class);
	}
}
