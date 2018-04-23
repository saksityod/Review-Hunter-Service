<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 	    
	protected $table = 'lportal.role_';
	protected $primaryKey = 'roleId';
	public $incrementing = true;
	public $timestamps = null;
	// //protected $guarded = array();
	// protected $fillable = [];
	// protected $hidden = [ 'created_dttm'];


}