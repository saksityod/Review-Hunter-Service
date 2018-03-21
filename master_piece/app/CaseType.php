<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseType extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	
    protected $table = 'case_type';
	protected $primaryKey = 'case_type_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = ['case_type','is_active','created_by', 'updated_by'];
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function doctorTarget()
    {
        return $this->hasMany('App\DoctorTarget');
    }

}