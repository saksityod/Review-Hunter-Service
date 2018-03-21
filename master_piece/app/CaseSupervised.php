<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use db;
class CaseSupervised extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;
    protected $table = 'case_supervised';
	protected $primaryKey = 'case_supervised_id';
	// public $incrementing = true;
	// public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = ['case_id','supervised_id','created_by', 'created_dttm'];
	// protected $hidden = ['created_by', 'created_dttm'];

	public function user(){
        return $this->hasOne('App\User','userId','supervised_id');
    }
}