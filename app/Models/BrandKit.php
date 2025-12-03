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
 * Class BrandKit
 * 
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string|null $logo_path
 * @property string|null $color_palette
 * @property string|null $typography
 * @property string|null $patterns
 * @property string|null $metadata
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property User $user
 * @property Collection|Campaign[] $campaigns
 *
 * @package App\Models
 */
class BrandKit extends Model
{
	use SoftDeletes;
	protected $table = 'brand_kits';
	public $incrementing = false;

	protected $casts = [
		'is_default' => 'bool'
	];

	protected $fillable = [
		'user_id',
		'name',
		'logo_path',
		'color_palette',
		'typography',
		'patterns',
		'metadata',
		'is_default'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function campaigns()
	{
		return $this->belongsToMany(Campaign::class)
					->withPivot('id', 'applied_settings')
					->withTimestamps();
	}
}
