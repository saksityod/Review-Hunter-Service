<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseStageAlert extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null; //'updated_dttm';
    protected $table = 'case_stage_alert';
	protected $primaryKey = 'case_stage_alert_id';
	public $incrementing = true;
	//public $timestamps = false;
	protected $guarded = array();
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}