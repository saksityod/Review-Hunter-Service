<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DoctorProcedure extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;
    protected $table = 'doctor_procedure';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	//protected $fillable = array();
	protected $hidden = ['created_by','created_dttm'];

	public function medical_procedure()
    {
        return $this->hasOne('App\MedicalProcedure','procedure_id','procedure_id');
    }
}