<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseStageDoc extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null; //'updated_dttm';
    protected $table = 'case_stage_doc';
	protected $primaryKey = 'case_stage_doc_id';
	public $incrementing = true;
	//public $timestamps = false;
	protected $guarded = array();
	// protected $fillable = ['contract_id','contract_path'];
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}