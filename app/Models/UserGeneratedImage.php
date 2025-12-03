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
 * Class UserGeneratedImage
 * 
 * @property string $id
 * @property string|null $user_id
 * @property string $campaign_id
 * @property string $campaign_template_id
 * @property string $photo_path
 * @property string $result_path
 * @property string|null $customizations
 * @property string|null $device_type
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $user_agent
 * @property int $downloads_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Campaign $campaign
 * @property CampaignTemplate $campaign_template
 * @property User|null $user
 * @property Collection|Share[] $shares
 *
 * @package App\Models
 */
class UserGeneratedImage extends Model
{
	use SoftDeletes;
	protected $table = 'user_generated_images';
	public $incrementing = false;

	protected $casts = [
		'downloads_count' => 'int'
	];

	protected $fillable = [
		'user_id',
		'campaign_id',
		'campaign_template_id',
		'photo_path',
		'result_path',
		'customizations',
		'device_type',
		'ip_address',
		'country',
		'user_agent',
		'downloads_count'
	];

	public function campaign()
	{
		return $this->belongsTo(Campaign::class);
	}

	public function campaign_template()
	{
		return $this->belongsTo(CampaignTemplate::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function shares()
	{
		return $this->hasMany(Share::class);
	}
}
