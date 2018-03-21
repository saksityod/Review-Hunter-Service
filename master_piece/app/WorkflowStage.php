<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkflowStage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	// const CREATED_AT = 'created_dttm';
	// const UPDATED_AT = 'updated_dttm';	    
	protected $table = 'workflow_stage';
	protected $primaryKey = 'workflow_stage_id';
	// public $incrementing = true;
	//public $timestamps = false;
	// //protected $guarded = array();
	// protected $fillable = ['case_group','is_active','created_by', 'updated_by'];
	// protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	
	public function toStage(){
        return $this->hasOne('App\Stage','stage_id','to_stage_id');
    }
    public function fromStage(){
        return $this->hasOne('App\Stage','stage_id','from_stage_id');
    }
}