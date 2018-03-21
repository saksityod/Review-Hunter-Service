<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticleDoc extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;
    protected $table = 'article_doc';
	protected $primaryKey = 'article_doc_id';
	public $incrementing = true;
	//public $timestamps = null;
	//protected $guarded = array();
	//protected $fillable = array('doctor_id','education_institution','education_level','education_degree');
	protected $hidden = ['created_by', 'created_dttm'];
}