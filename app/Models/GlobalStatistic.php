<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class GlobalStatistic
 * 
 * @property string $id
 * @property int $total_users
 * @property int $active_users
 * @property int $total_campaigns
 * @property int $active_campaigns
 * @property int $total_generations
 * @property int $total_downloads
 * @property int $total_ai_generations
 * @property int $total_video_jobs
 * @property Carbon $stats_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class GlobalStatistic extends Model
{
	protected $table = 'global_statistics';
	public $incrementing = false;

	protected $casts = [
		'total_users' => 'int',
		'active_users' => 'int',
		'total_campaigns' => 'int',
		'active_campaigns' => 'int',
		'total_generations' => 'int',
		'total_downloads' => 'int',
		'total_ai_generations' => 'int',
		'total_video_jobs' => 'int',
		'stats_date' => 'datetime'
	];

	protected $fillable = [
		'total_users',
		'active_users',
		'total_campaigns',
		'active_campaigns',
		'total_generations',
		'total_downloads',
		'total_ai_generations',
		'total_video_jobs',
		'stats_date'
	];
}
