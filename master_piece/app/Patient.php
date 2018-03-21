<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	    
	protected $table = 'patient';
	protected $primaryKey = 'patient_id';
	// public $incrementing = true;
	//public $timestamps = false;
	// //protected $guarded = array();
	// protected $fillable = ['case_group','is_active','created_by', 'updated_by'];
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function social(){
        return $this->hasMany('App\PatientSocialMedia','patient_id','patient_id');
    }
    public function surgery(){
        return $this->hasMany('App\SurgeryHistory','patient_id','patient_id');
    }
}