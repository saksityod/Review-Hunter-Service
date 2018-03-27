<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
// if (isset($_SERVER['HTTP_ORIGIN'])) {
// 	header('Access-Control-Allow-Credentials: true');
// 	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
// 	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
// 	header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, useXDomain, withCredentials');
// 	header('Keep-Alive: off');
// }

//Route::resource('authenticate', 'AuthenticateController', ['only' => ['index']]);
Route::group(['middleware' => 'cors'], function()
{	
	/*Session*/ 
	Route::get('session','AuthenticateController@index');
	Route::post('session', 'AuthenticateController@authenticate');
	Route::get('session/debug', 'AuthenticateController@debug');
	Route::delete('session', 'AuthenticateController@destroy');

	/*DoctorProfile*/
	Route::group(['prefix'=>'doctor_profile'],function(){
		Route::get('get_current_date','DoctorProfileController@get_current_date');
		Route::get('list_doctor','DoctorProfileController@list_doctor');
		Route::get('list_doctor_name','DoctorProfileController@list_doctor_name');
		Route::get('list_medical_procedure','DoctorProfileController@list_medical_procedure');
		Route::get('search_doctor','DoctorProfileController@search_doctor');
		//Route::get('show_doctor','DoctorProfileController@show_doctor');// safety
		Route::get('get_doctor','DoctorProfileController@get_doctor');
		Route::post('crud','DoctorProfileController@crud');
		Route::post('destroy_doctor','DoctorProfileController@destroy_doctor');
		Route::post('destroy_edu','DoctorProfileController@destroy_edu');
		Route::post('destroy_work','DoctorProfileController@destroy_work');
	});
	
	//DoctorTarget
	Route::group(['prefix'=>'doctor_target'],function(){
		Route::get('get_user','DoctorTargetController@get_user');
		Route::get('get_current_date','DoctorTargetController@get_current_date');
		Route::get('list_doctor','DoctorTargetController@list_doctor');
		Route::get('list_doctor_all','DoctorTargetController@list_doctor_all');
		Route::get('list_medical_procedure','DoctorTargetController@list_medical_procedure');
		Route::get('list_medical_procedure_all','DoctorTargetController@list_medical_procedure_all');
		Route::get('list_year','DoctorTargetController@list_year');
		Route::get('list_case','DoctorTargetController@list_case');
		Route::get('search_target','DoctorTargetController@search_target');
		Route::post('destroy_one','DoctorTargetController@destroy_one');
		Route::get('get_target','DoctorTargetController@get_target');
		Route::post('cru','DoctorTargetController@cru');
	});
	
	/*CaseType*/
	Route::group(['prefix'=>'case_type'],function(){
		Route::get('getAll','CaseTypeController@get_all');
		Route::get('getOne/{id}','CaseTypeController@get_one');

		Route::post('new','CaseTypeController@create');
		Route::post('update/{id}','CaseTypeController@update');
		Route::post('delete/{id}','CaseTypeController@destroy');
	});

/*CaseGroup*/
	Route::group(['prefix'=>'case_group'],function(){
		Route::get('getAll','CaseGroupController@get_all');
		Route::get('getOne','CaseGroupController@get_one');

		Route::post('cu','CaseGroupController@cu');
		Route::post('delete','CaseGroupController@destroy');
	});

	/*medical_procedure*/
	Route::group(['prefix'=>'medical_procedure'],function(){
		Route::get('getAll','MedicalProcedureController@get_all');
		Route::get('getOne/{id}','MedicalProcedureController@get_one');

		Route::post('new','MedicalProcedureController@create');
		Route::post('update/{id}','MedicalProcedureController@update');
		Route::post('delete/{id}','MedicalProcedureController@destroy');
	});
	
	/*social _media*/
	Route::group(['prefix'=>'social_media'],function(){
		Route::get('getAll','SocialMediaController@get_all');
		Route::get('getOne/{id}','SocialMediaController@get_one');

		Route::post('new','SocialMediaController@create');
		Route::post('update/{id}','SocialMediaController@update');
		Route::post('delete/{id}','SocialMediaController@destroy');
	});

	/* B&A*/
	Route::group(['prefix'=>'ba'],function(){
		Route::get('sectionRole'		,'BAController@section_role');
		Route::get('getCaseFile','BAController@get_case_file');
		Route::get('getFolder/{case_id}','BAController@get_folder');
		Route::get('getCaseFolder/{case_id}','BAController@get_case_folder');
		Route::get('getOnLoad'		,'BAController@get_dataOnload');
		Route::get('getUser'		,'BAController@get_user');
		Route::get('getSupervisedUser'		,'BAController@get_supervised_user');
		Route::get('getProvince'	,'BAController@get_province');
		Route::get('getAmphur'		,'BAController@get_amphur');
		Route::get('getDistrict'	,'BAController@get_district');
		Route::get('selectPathAll'	,'BaController@select_path_all');
		Route::get('getNewCaseStage','BaController@get_new_case_stage');
		Route::get('getStage'		,'BaController@get_stage');
		Route::get('getUserAlert'	,'BaController@get_user_alert');
		Route::get('action_to'		,'BaController@action_to');
		Route::get('sendTo'			,'BAController@send_to_stage');
		Route::get('getCaseList'	,'BaController@get_caseList');
		Route::get('getOneCase'		,'BaController@get_oneCase');
		Route::get('getOnePatient'	,'BaController@get_onePatient');
		Route::get('getFolderSummary','BaController@get_folderSummary');
		Route::get('downloadCaseStageDoc/{case_stage_id}'	,'BaController@download_case_stage_doc');

		Route::post('updateFolder'	,'BaController@updateFolder');
		Route::post('destoryFolder'	,'BAController@destoryFolder');
		Route::post('cu'			,'BaController@cu');
		Route::post('cu_contract'	,'BaController@cu_contract');
		Route::post('makeDirectory'	,'BaController@make_directory');
		Route::post('deleteFile'	,'BaController@delete_file');
		Route::post('delRec'		,'BaController@del_rec');
		Route::post('importFile'	,'BaController@import_file');

		//ba
		 Route::post('destroy_social_media','BAController@destroy_social_media');
		 Route::post('destroy_surgery_history','BAController@destroy_social_media');
		 Route::post('destroy_case_price','BAController@destroy_social_media');
		 Route::post('destroy_case_social_media','BAController@destroy_social_media');
		 Route::post('destroy_case_appointment','BAController@destroy_case_appointment');
		 Route::post('destroy_case_contract','BAController@destroy_case_contract');
		 Route::post('destroy_case_pr','BAController@destroy_case_pr');
		 Route::post('destroy_case_article','BAController@destroy_case_article');

		 Route::get('download_case_contract/{id}','WriterController@download_case_contract');
		 Route::get('download_case_article/{id}','WriterController@download_case_article');
	});

		// To Do List //
	Route::group(['prefix'=>'todo'],function(){
		Route::get('search','TodoController@search');
	  	Route::get('auto_doctor','TodoController@auto_doctor');
	  	Route::get('auto_patient','TodoController@auto_patient');
	  	Route::get('status_list','TodoController@status_list');
	  	Route::get('procedure_list','TodoController@procedure_list');
	  	Route::get('auto_user','TodoController@auto_user');

	  	Route::get('check_user','TodoController@check_user');
	});

	//Writer
 	Route::group(['prefix'=>'writer'],function(){
	  	Route::get('list_writer','WriterController@list_writer');
	  	Route::get('list_medical_procedure','WriterController@list_medical_procedure');
	  	Route::get('list_doctor_article','WriterController@list_doctor_article');
	  	Route::get('list_doctor','WriterController@list_doctor');
	  	Route::get('send_to','WriterController@send_to');


	  	Route::get('list_article_type','WriterController@list_article_type');
	  	Route::get('list_user_alert','WriterController@list_user_alert');
	  	Route::get('search_writer','WriterController@search_writer');
	  	Route::post('cu','WriterController@cu');
	  	Route::post('import/{id}','WriterController@import');
	  	Route::get('download_article_stage_doc/{id}','WriterController@download_article_stage_doc');
	  	Route::get('download_article_doc/{id}','WriterController@download_article_doc');
	  	Route::get('current_action','WriterController@current_action');
	  	Route::get('action_to','WriterController@action_to');
	  	Route::get('send_to_stage','WriterController@send_to_stage');
	  	Route::get('show','WriterController@show');

	  	Route::get('check_disabled','WriterController@check_disabled');

	  	Route::get('list_to_user','WriterController@list_to_user');
 	});
	
	//Report
	Route::group(['prefix'=>'report'],function(){
	  	Route::get('case_list_year','ReportController@case_list_year');
	  	Route::get('case_list_month','ReportController@case_list_month');

	  	Route::get('writer_list_writer','ReportController@writer_list_writer');

	  	Route::get('case_list_doctor','ReportController@case_list_doctor');

	  	Route::get('case_list_case_type','ReportController@case_list_case_type');

	  	Route::get('case_followup_year','ReportController@case_followup_year');
	  	Route::get('case_followup_case_type','ReportController@case_followup_case_type');
	  	Route::get('case_followup_case_group','ReportController@case_followup_case_group');

	  	Route::get('list_selector_time','ReportController@list_selector_time');

	  	Route::get('api_report','JasperController@api_report');
	 });



	// Route::get('404', ['as' => 'notfound', function () {
	// 	return response()->json(['status' => '404']);
	// }]);

	// Route::get('405', ['as' => 'notallow', function () {
	// 	return response()->json(['status' => '405']);
	// }]);	

});



