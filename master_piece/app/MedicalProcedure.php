<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MedicalProcedure extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';
    protected $table = 'medical_procedure';
	protected $primaryKey = 'procedure_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	//protected $fillable = array('doctor_id','education_institution','education_level','education_degree');
    protected $fillable = ['procedure_name','is_active','created_by', 'updated_by'];
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function doctorProcedure()
    {
        return $this->hasMany('App\DoctorProcedure','procedure_id');
    }

    public function doctorTarget()
    {
        return $this->hasMany('App\DoctorTarget','procedure_id');
    }

}