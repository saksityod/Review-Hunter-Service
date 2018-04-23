<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;	    
	protected $table = 'folder';
	protected $primaryKey = 'folder_id';
	public $incrementing = true;
	// public $timestamps = null;
	// //protected $guarded = array();
	protected $fillable = ['folder_name','created_by','folder_screen_name','is_active', 'is_open','is_pass','folder_parent_id'];
	protected $hidden = [ 'created_dttm'];

	public function subFolder(){
        return $this->hasMany('App\Folder','folder_parent_id','folder_id');
    }
    public function parentFolder(){
        return $this->hasOne('App\Folder','folder_id','folder_parent_id');
    }
    public function caseFolder(){
        return $this->hasOne('App\CaseFolder','folder_id','folder_id');
    }
    public function caseSubFolder(){
        return $this->hasOne('App\CaseFolder','folder_id','folder_id');
    }
    public function caseFile(){
        return $this->hasMany('App\CaseFile','folder_id','folder_id');
    }

}