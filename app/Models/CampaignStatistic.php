<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CampaignStatistic
 * 
 * @property string $id
 * @property string $campaign_id
 * @property int $total_views
 * @property int $total_generations
 * @property int $total_downloads
 * @property int $total_shares
 * @property string|null $countries
 * @property string|null $devices
 * @property string|null $platforms
 * @property string|null $top_referrers
 * @property Carbon|null $stats_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Campaign $campaign
 *
 * @package App\Models
 */
class CampaignStatistic extends Model
{
	protected $table = 'campaign_statistics';
	public $incrementing = false;

	protected $casts = [
		'total_views' => 'int',
		'total_generations' => 'int',
		'total_downloads' => 'int',
		'total_shares' => 'int',
		'stats_date' => 'datetime'
	];

	protected $fillable = [
		'campaign_id',
		'total_views',
		'total_generations',
		'total_downloads',
		'total_shares',
		'countries',
		'devices',
		'platforms',
		'top_referrers',
		'stats_date'
	];

	public function campaign()
	{
		return $this->belongsTo(Campaign::class);
	}
}
