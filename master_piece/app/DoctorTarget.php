<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DoctorTarget extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
     
    const CREATED_AT = 'created_dttm';
    const UPDATED_AT = 'updated_dttm';
    protected $table = 'doctor_target';
    protected $primaryKey = 'doctor_target_id';
    public $incrementing = true;
    //public $timestamps = false;
    //protected $guarded = array();
    protected $fillable = array('doctor_id','year','is_active','procedure_id','case_type_id',
                                'target_month1','target_month2','target_month3','target_month4',
                                'target_month5','target_month6','target_month7','target_month8',
                                'target_month9','target_month10','target_month11','target_month12'
                            );
    protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

    public function doctor(){
        return $this->hasOne('App\Doctor','doctor_id','doctor_id');
    }
    public function caseType() {
        return $this->hasOne('App\CaseType','case_type_id','case_type_id');
    }
    public function doctorProcedure() {
        return $this->hasOne('App\DoctorProcedure','procedure_id','procedure_id');
    }
    public function doctorTargetAlert() {
        return $this->hasMany('App\DoctorTargetAlert','doctor_target_id','doctor_target_id');
    }

}