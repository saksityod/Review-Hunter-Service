<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SocialMedia extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = 'updated_dttm';	 
    protected $table = 'social_media';
	protected $primaryKey = 'social_media_id';
	public $incrementing = true;
	//public $timestamps = false;
	//protected $guarded = array();
	protected $fillable = ['social_media_name','is_active','created_by', 'updated_by'];
	protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];

	public function doctorTarget()
    {
        return $this->hasMany('App\DoctorTarget');
    }

}