<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class StatisticsDaily
 * 
 * @property string $id
 * @property string $campaign_id
 * @property Carbon $stats_date
 * @property int $views
 * @property int $generations
 * @property int $downloads
 * @property int $shares
 * @property string|null $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Campaign $campaign
 *
 * @package App\Models
 */
class StatisticsDaily extends Model
{
	protected $table = 'statistics_daily';
	public $incrementing = false;

	protected $casts = [
		'stats_date' => 'datetime',
		'views' => 'int',
		'generations' => 'int',
		'downloads' => 'int',
		'shares' => 'int'
	];

	protected $fillable = [
		'campaign_id',
		'stats_date',
		'views',
		'generations',
		'downloads',
		'shares',
		'details'
	];

	public function campaign()
	{
		return $this->belongsTo(Campaign::class);
	}
}
