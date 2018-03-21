<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseStage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null; //'updated_dttm';
    protected $table = 'case_stage';
	protected $primaryKey = 'case_stage_id';
	public $incrementing = true;
	//public $timestamps = false;
	// protected $guarded = array();
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function patientCase(){
        return $this->hasOne('App\PatientCase','case_id','case_id');
    }
    public function fromUser(){
        return $this->hasOne('App\User','userId','from_user_id');
    }
    public function toUser(){
        return $this->hasOne('App\User','userId','to_user_id');
    }
    public function fromStage(){
        return $this->hasOne('App\Stage','stage_id','from_stage_id');
    }
    public function toStage(){
        return $this->hasOne('App\Stage','stage_id','to_stage_id');
    }

}