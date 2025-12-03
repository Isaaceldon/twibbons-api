<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AiGeneration
 * 
 * @property string $id
 * @property string $user_id
 * @property string|null $campaign_id
 * @property string $ai_provider_id
 * @property string $prompt
 * @property string|null $logo_path
 * @property string|null $parameters
 * @property string $status
 * @property string|null $results
 * @property float|null $generation_time
 * @property int $attempts
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property AiProvider $ai_provider
 * @property Campaign|null $campaign
 * @property User $user
 * @property Collection|CampaignTemplate[] $campaign_templates
 *
 * @package App\Models
 */
class AiGeneration extends Model
{
	protected $table = 'ai_generations';
	public $incrementing = false;

	protected $casts = [
		'generation_time' => 'float',
		'attempts' => 'int',
		'started_at' => 'datetime',
		'completed_at' => 'datetime'
	];

	protected $fillable = [
		'user_id',
		'campaign_id',
		'ai_provider_id',
		'prompt',
		'logo_path',
		'parameters',
		'status',
		'results',
		'generation_time',
		'attempts',
		'error_message',
		'started_at',
		'completed_at'
	];

	public function ai_provider()
	{
		return $this->belongsTo(AiProvider::class);
	}

	public function campaign()
	{
		return $this->belongsTo(Campaign::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function campaign_templates()
	{
		return $this->hasMany(CampaignTemplate::class);
	}
}
