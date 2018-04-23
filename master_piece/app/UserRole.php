<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 	    
	protected $table = 'lportal.users_roles';
	protected $primaryKey = 'roleId';
	public $incrementing = true;
	public $timestamps = null;
	// //protected $guarded = array();
	// protected $fillable = [];
	// protected $hidden = [ 'created_dttm'];


	public function user(){
        return $this->hasOne('App\User','userId','userId');
    }
}