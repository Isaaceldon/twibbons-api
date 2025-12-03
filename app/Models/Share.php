<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Share
 * 
 * @property string $id
 * @property string $user_generated_image_id
 * @property string $platform
 * @property string|null $share_url
 * @property string|null $metadata
 * @property string|null $ip_address
 * @property string|null $country
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property UserGeneratedImage $user_generated_image
 *
 * @package App\Models
 */
class Share extends Model
{
	protected $table = 'shares';
	public $incrementing = false;

	protected $fillable = [
		'user_generated_image_id',
		'platform',
		'share_url',
		'metadata',
		'ip_address',
		'country'
	];

	public function user_generated_image()
	{
		return $this->belongsTo(UserGeneratedImage::class);
	}
}
