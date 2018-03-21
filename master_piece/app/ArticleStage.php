<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleStage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null; //'updated_dttm';
    protected $table = 'article_stage';
	protected $primaryKey = 'article_stage_id';
	public $incrementing = true;
	//public $timestamps = false;
	protected $guarded = array();
	//protected $hidden = ['created_by', 'updated_by', 'created_dttm', 'updated_dttm'];
}