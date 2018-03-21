<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseContractDoc extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;
    protected $table = 'case_contract_doc';
	protected $primaryKey = 'case_contract_doc_id';
	public $incrementing = true;
	//public $timestamps = null;
	//protected $guarded = array();
	protected $fillable = ['contract_id','contract_path'];
	protected $hidden = ['created_by', 'created_dttm'];
}