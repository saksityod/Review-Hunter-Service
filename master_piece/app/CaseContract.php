<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseContract extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';
    protected $table = 'case_contract';
	protected $primaryKey = 'contract_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	//protected $fillable = array('doctor_id','education_institution','education_level','education_degree');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

    public function caseContractDoc(){
        return $this->hasMany('App\CaseContractDoc','contract_id','contract_id');
    }
}