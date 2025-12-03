<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ModelHasRole
 * 
 * @property string $role_id
 * @property string $model_type
 * @property string $model_id
 * 
 * @property Role $role
 *
 * @package App\Models
 */
class ModelHasRole extends Model
{
	protected $table = 'model_has_roles';
	public $incrementing = false;
	public $timestamps = false;

	public function role()
	{
		return $this->belongsTo(Role::class);
	}
}
