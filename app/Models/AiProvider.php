<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AiProvider
 * 
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $api_endpoint
 * @property string $api_key_encrypted
 * @property string|null $configuration
 * @property bool $is_active
 * @property int $priority
 * @property int $rate_limit
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|AiGeneration[] $ai_generations
 *
 * @package App\Models
 */
class AiProvider extends Model
{
	protected $table = 'ai_providers';
	public $incrementing = false;

	protected $casts = [
		'is_active' => 'bool',
		'priority' => 'int',
		'rate_limit' => 'int',
		'last_used_at' => 'datetime'
	];

	protected $fillable = [
		'name',
		'slug',
		'api_endpoint',
		'api_key_encrypted',
		'configuration',
		'is_active',
		'priority',
		'rate_limit',
		'last_used_at'
	];

	public function ai_generations()
	{
		return $this->hasMany(AiGeneration::class);
	}
}
