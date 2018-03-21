<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	// const CREATED_AT = 'created_dttm';
	// const UPDATED_AT = 'updated_dttm';	    
	protected $table = 'stage';
	protected $primaryKey = 'stage_id';
	// public $incrementing = true;
	//public $timestamps = false;
	// //protected $guarded = array();
	// protected $fillable = ['case_group','is_active','created_by', 'updated_by'];
	// protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	
 	public function workflowStage(){
        return $this->hasMany('App\WorkflowStage','from_stage_id');
    }

}