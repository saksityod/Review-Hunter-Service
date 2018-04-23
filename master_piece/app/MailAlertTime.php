<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MailAlertTime extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 	    
	protected $table = 'mail_alert_time';
	protected $primaryKey = 'mail_alert_time_id';
	public $incrementing = true;
	public $timestamps = null;
	// //protected $guarded = array();
	// protected $fillable = [];
	protected $hidden = [ 'created_dttm'];

}