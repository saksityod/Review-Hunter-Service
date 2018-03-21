<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CaseArticleDoc extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
	 
	const CREATED_AT = 'created_dttm';
	const UPDATED_AT = null;
    protected $table = 'case_article_doc';
	protected $primaryKey = 'case_article_doc_id';
	public $incrementing = true;
	//public $timestamps = null;
	//protected $guarded = array();
	protected $fillable = ['case_article_id','article_path'];
	protected $hidden = ['created_by', 'created_dttm'];
}