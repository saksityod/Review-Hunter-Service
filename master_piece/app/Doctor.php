<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';
    protected $table = 'doctor';
	protected $primaryKey = 'doctor_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = array('doctor_name','gender','expertise','is_active');
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function doctor_education()
    {
        return $this->hasMany('App\DoctorEducation');
    }
    public function doctor_work()
    {
        return $this->hasMany('App\DoctorWork');
    }
    public function doctor_procedure()
    {
        return $this->hasMany('App\DoctorProcedure');
    }
    public function doctor_target()
    {
        return $this->hasMany('App\DoctorTarget');
    }
}