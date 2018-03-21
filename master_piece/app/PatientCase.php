<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PatientCase extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';
    protected $table = 'patient_case';
	protected $primaryKey = 'case_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	// protected $fillable = array('case_id');
	protected $hidden = ['doctor_id','case_group_id','case_type_id','procedure_id',
                        'created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function patient(){
        return $this->hasOne('App\Patient','patient_id','patient_id');
    }
    public function patientSocialMedia(){
        return $this->hasMany('App\PatientSocialMedia','patient_id','patient_id');
    }
    public function patientSurgery(){
        return $this->hasMany('App\SurgeryHistory','patient_id','patient_id');
    }
    public function stage(){
        return $this->hasOne('App\Stage','stage_id','case_stage_id');
    }

    /*  */
    public function caseType(){
        return $this->hasOne('App\CaseType','case_type_id','case_type_id');
    }
    public function caseGroup(){
        return $this->hasOne('App\CaseGroup','case_group_id','case_group_id');
    }
    public function procedure(){
        return $this->hasOne('App\MedicalProcedure','procedure_id','procedure_id');
    }
    public function doctor(){
        return $this->hasOne('App\Doctor','doctor_id','doctor_id');
    }

    /*  */
    public function caseSupervised(){
        return $this->hasMany('App\CaseSupervised','case_id','case_id');
    }
    public function casePrice(){
        return $this->hasMany('App\CasePrice','case_id','case_id');
    }
    public function caseFollowUp(){
        return $this->hasMany('App\CaseFollowUp','case_id','case_id');
    }
    public function caseSocialMedia(){
        return $this->hasMany('App\CaseSocialMedia','case_id','case_id');
    }
    public function caseCoordinate(){
        return $this->hasMany('App\CaseCoordinate','case_id','case_id');
    }
    public function caseAppointment(){
        return $this->hasMany('App\CaseAppointment','case_id','case_id');
    }
    public function caseContract(){
        return $this->hasMany('App\CaseContract','case_id','case_id');
    }
    public function casePr(){
        return $this->hasMany('App\CasePr','case_id','case_id');
    }
    public function caseArticle(){
        return $this->hasMany('App\CaseArticle','case_id','case_id');
    }

    public function caseFile(){
        return $this->hasMany('App\CaseFile','case_id','case_id');
    }
}