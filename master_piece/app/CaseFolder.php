<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseFolder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;	    
	protected $table = 'case_folder';
	protected $primaryKey = 'case_folder_id';
	public $incrementing = true;
	//public $timestamps = false;
	// //protected $guarded = array();
	// protected $fillable = ['case_group','is_active','created_by', 'updated_by'];
	// protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	
    public function patientCase(){
        return $this->hasOne('App\PatientCase','case_id','case_id');
    }
    public function folder(){
        return $this->hasOne('App\Folder','folder_id','folder_id');
    }
    public function subFolder(){
        return $this->hasOne('App\Folder','folder_id','folder_id');
    }

}