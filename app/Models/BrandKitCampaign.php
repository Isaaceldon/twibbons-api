<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BrandKitCampaign
 * 
 * @property string $id
 * @property string $brand_kit_id
 * @property string $campaign_id
 * @property string|null $applied_settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property BrandKit $brand_kit
 * @property Campaign $campaign
 *
 * @package App\Models
 */
class BrandKitCampaign extends Model
{
	protected $table = 'brand_kit_campaign';
	public $incrementing = false;

	protected $fillable = [
		'brand_kit_id',
		'campaign_id',
		'applied_settings'
	];

	public function brand_kit()
	{
		return $this->belongsTo(BrandKit::class);
	}

	public function campaign()
	{
		return $this->belongsTo(Campaign::class);
	}
}
