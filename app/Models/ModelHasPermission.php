<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ModelHasPermission
 * 
 * @property string $permission_id
 * @property string $model_type
 * @property string $model_id
 * 
 * @property Permission $permission
 *
 * @package App\Models
 */
class ModelHasPermission extends Model
{
	protected $table = 'model_has_permissions';
	public $incrementing = false;
	public $timestamps = false;

	public function permission()
	{
		return $this->belongsTo(Permission::class);
	}
}
