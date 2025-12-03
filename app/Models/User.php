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
 * Class User
 * 
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $photo_url
 * @property string $language
 * @property string|null $country
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * 
 * @property Collection|AiGeneration[] $ai_generations
 * @property Collection|BrandKit[] $brand_kits
 * @property Collection|Campaign[] $campaigns
 * @property Collection|UserGeneratedImage[] $user_generated_images
 * @property Collection|VideoJob[] $video_jobs
 *
 * @package App\Models
 */
class User extends Model
{
	use SoftDeletes;
	protected $table = 'users';
	public $incrementing = false;

	protected $casts = [
		'email_verified_at' => 'datetime'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'name',
		'email',
		'email_verified_at',
		'password',
		'photo_url',
		'language',
		'country',
		'remember_token'
	];

	public function ai_generations()
	{
		return $this->hasMany(AiGeneration::class);
	}

	public function brand_kits()
	{
		return $this->hasMany(BrandKit::class);
	}

	public function campaigns()
	{
		return $this->hasMany(Campaign::class);
	}

	public function user_generated_images()
	{
		return $this->hasMany(UserGeneratedImage::class);
	}

	public function video_jobs()
	{
		return $this->hasMany(VideoJob::class);
	}
}
