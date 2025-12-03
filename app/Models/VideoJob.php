<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class VideoJob
 * 
 * @property string $id
 * @property string $user_id
 * @property string $video_template_id
 * @property string|null $logo_path
 * @property string|null $text
 * @property string|null $parameters
 * @property string $status
 * @property string|null $output_path
 * @property float|null $processing_time
 * @property int $progress
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User $user
 * @property VideoTemplate $video_template
 *
 * @package App\Models
 */
class VideoJob extends Model
{
	protected $table = 'video_jobs';
	public $incrementing = false;

	protected $casts = [
		'processing_time' => 'float',
		'progress' => 'int',
		'started_at' => 'datetime',
		'completed_at' => 'datetime'
	];

	protected $fillable = [
		'user_id',
		'video_template_id',
		'logo_path',
		'text',
		'parameters',
		'status',
		'output_path',
		'processing_time',
		'progress',
		'error_message',
		'started_at',
		'completed_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function video_template()
	{
		return $this->belongsTo(VideoTemplate::class);
	}
}
