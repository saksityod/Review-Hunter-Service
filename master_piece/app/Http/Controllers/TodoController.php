<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use File;
use Validator;
use Excel;
use Mail;
use Config;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TodoController extends Controller
{

	public function __construct()
	{
	  $this->middleware('jwt.auth');
	}

	public function auto_patient(Request $request)
	{
		$items = DB::select("
			select patient_id, patient_name 
			from patient
			where patient_name like ?
			order by patient_name
		", array('%'.$request->patient_name.'%'));
		
		return response()->json($items);
	}
	
	public function status_list(Request $request)
	{
		$items = DB::select("
			select distinct status 
			from workflow_stage 
			order by status 
		");
		
		return response()->json($items);
	}	
	
	public function procedure_list(Request $request)
	{
		$items = DB::select("
			select procedure_id, procedure_name 
			from medical_procedure 
			where is_active = 1 
			order by procedure_name
		");
		
		return response()->json($items);
	}		
	
	public function auto_doctor(Request $request)
	{
		$items = DB::select("
			select doctor_id, doctor_name 
			from doctor 
			where is_active = 1 
			and doctor_name like ?
			order by doctor_name
		", array('%'.$request->doctor_name.'%'));
		
		return response()->json($items);
	}		
	
	// public function auto_user(Request $request)
	// {
	// 	$items = DB::select("
	// 		SELECT userId, concat(firstName, ' ', lastName) as pic_name
	// 		FROM lportal.user_
	// 		where concat(firstName, ' ', lastName) like ?
	// 	", array('%'.$request->pic_name.'%'));
		
	// 	return response()->json($items);
	// }

	public function check_user(Request $request)
	{
		$is_admin = DB::select("
			select roleId
			from lportal.users_roles
			where userId = ?  
			and roleId in (22301,22302,22306,22309)
			", array(Auth::user()->userId));

		if (empty($is_admin)) {
			$items = DB::select("
				SELECT u.userId, concat(u.firstName, ' ', u.lastName) as pic_name
				FROM lportal.user_ u
				where u.userId = ?
			", array(Auth::user()->userId)); 
			return response()->json(['status' => 400, 'data' => $items]);
		}

		return response()->json(['status' => 200]);
	}

	public function auto_user(Request $request)
	{
		$is_admin = DB::select("
			select roleId
			from lportal.users_roles
			where userId = ?  
			and roleId in (22301,22302,22306,22309)
			", array(Auth::user()->userId));

		if (empty($is_admin)) {
			$items = DB::select("
				SELECT u.userId, concat(u.firstName, ' ', u.lastName) as pic_name
				FROM lportal.user_ u
				where u.userId = ?
				", array(Auth::user()->userId));   
		} else {
			$items = DB::select("
				SELECT u.userId, concat(u.firstName, ' ', u.lastName) as pic_name
				FROM lportal.user_ u
				left outer join lportal.users_roles r
				on u.userId = r.userId
				where concat(u.firstName, ' ', u.lastName) like ?
				and r.roleId in (22301,22302,22303,22304,22305,22306,22307,22308,22309,22310,22311)
				group by u.userId
				", array('%'.$request->pic_name.'%'));
		}
		return response()->json($items);
	}
	
	// public function search(Request $request)
	// {
	// 	$qinput = array();
	// 	$query = "
	// 		SELECT pt.patient_name as patient_name, mp.procedure_name as procedure_name,  concat(us.firstName, ' ', us.lastName) as pic_name, 
	// 		t_s.stage_name as task_name, cs.status as status, cs.plan_date as plan_date, cs.actual_date as_actual_date, dr.doctor_name as doctor_name
	// 		from patient pt, patient_case pc, medical_procedure mp , case_stage cs, doctor dr, lportal.user_ us, workflow_stage pwf,  workflow_stage cwf, stage t_s
	// 		where pt.patient_id = pc.patient_id
	// 		and pc.case_id = cs.case_id
	// 		and pc.procedure_id = mp.procedure_id
	// 		and pc.doctor_id = dr.doctor_id
	// 		and cs.to_user_id = us.userId
	// 		and pc.workflow_stage_id = pwf.workflow_stage_id
	// 		and cs.workflow_stage_id = cwf.workflow_stage_id
	// 		and cwf.to_stage_id = t_s.stage_id
	// 	";
		
	// 	empty($request->patient_id) ?: ($query .= " and pt.patient_id = ? " AND $qinput[] = $request->patient_id);
	// 	empty($request->status) ?: ($query .= " and cwf.status = ? " AND $qinput[] = $request->status);
	// 	empty($request->procedure_id) ?: ($query .= " and pc.procedure_id = ? " AND $qinput[] = $request->procedure_id);
	// 	empty($request->doctor_id) ?: ($query .= " pc.doctor_id = ? " AND $qinput[] = $request->doctor_id);
	// 	empty($request->user_id) ?: ($query .= " and cs.to_user_id  = ? " AND $qinput[] = $request->user_id);
			
	// 	$query .= "
	// 		union 
	// 		select 'N/A' as patient_name, mp.procedure_name as procedure_name, concat(us.firstName, ' ', us.lastName) as pic_name, 
	// 		t_s.stage_name as task_name, ars.status as status, ars.plan_date as plan_date, ars.actual_date as_actual_date, dr.doctor_name as doctor_name
	// 		from article ac, medical_procedure mp, article_stage ars, doctor dr, lportal.user_ us, workflow_stage awf, workflow_stage aswf, stage t_s
	// 		where ac.article_id = ars.article_id
	// 		and ac.procedure_id = mp.procedure_id
	// 		and ac.doctor_id = dr.doctor_id
	// 		and ars.to_user_id = us.userId
	// 		and ac.workflow_stage_id = awf.workflow_stage_id
	// 		and ars.workflow_stage_id = aswf.workflow_stage_id
	// 		and aswf.to_stage_id = t_s.stage_id
	// 	";
	// 	empty($request->status) ?: ($query .= " and aswf.status = ? " AND $qinput[] = $request->status);
	// 	empty($request->procedure_id) ?: ($query .= " and ac.procedure_id = ? " AND $qinput[] = $request->procedure_id);
	// 	empty($request->doctor_id) ?: ($query .= " ac.doctor_id = ? " AND $qinput[] = $request->doctor_id);
	// 	empty($request->user_id) ?: ($query .= " and as.to_user_id  = ? " AND $qinput[] = $request->user_id);	

	// 	$items = DB::select($query,$qinput);
		
	// 	// Get the current page from the url if it's not set default to 1
	// 	empty($request->page) ? $page = 1 : $page = $request->page;
		
	// 	// Number of items per page
	// 	empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
	// 	$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

	// 	// Get only the items you need using array_slice (only get 10 items since that's what you need)
	// 	$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
	// 	// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
	// 	$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);				
		
	// 	return response()->json($result);
		
	// }

	public function search(Request $request)
	{
		$qinput = array();
		$query = "
			select pt.patient_name as patient_name, 'N/A' as article_code, mp.procedure_name as procedure_name,  concat(us.firstName, ' ', us.lastName) as pic_name, 
			t_s.stage_name as task_name, cs.status as status, cs.plan_date as plan_date, cs.actual_date as_actual_date, dr.doctor_name as doctor_name, pc.vn_no
			from patient pt, patient_case pc, medical_procedure mp , case_stage cs, doctor dr, lportal.user_ us, stage t_s
			where pt.patient_id = pc.patient_id
			and pc.case_id = cs.case_id
			and pc.procedure_id = mp.procedure_id
			and pc.doctor_id = dr.doctor_id
			and cs.to_user_id = us.userId
			and cs.to_stage_id = t_s.stage_id
			and pc.case_stage_id = cs.case_stage_id
		";
		
		empty($request->patient_id) ?: ($query .= " and pt.patient_id = ? " AND $qinput[] = $request->patient_id);
		empty($request->status) ?: ($query .= " and cs.status = ? " AND $qinput[] = $request->status);
		empty($request->procedure_id) ?: ($query .= " and pc.procedure_id = ? " AND $qinput[] = $request->procedure_id);
		empty($request->doctor_id) ?: ($query .= " and pc.doctor_id = ? " AND $qinput[] = $request->doctor_id);
		empty($request->user_id) ?: ($query .= " and cs.to_user_id  = ? " AND $qinput[] = $request->user_id);
			
		// $query .= "
		// 	union
		// 	select 'N/A' as patient_name, ac.article_name as article_name, mp.procedure_name as procedure_name, concat(us.firstName, ' ', us.lastName) as pic_name, 
		// 	t_s.stage_name as task_name, ars.status as status, ars.plan_date as plan_date, ars.actual_date as_actual_date, dr.doctor_name as doctor_name, 'N/A' as vn_no
		// 	from article ac, medical_procedure mp, article_stage ars, doctor dr, lportal.user_ us, stage t_s
		// 	where ac.article_id = ars.article_id
		// 	and ac.procedure_id = mp.procedure_id
		// 	and ac.doctor_id = dr.doctor_id
		// 	and ars.to_user_id = us.userId
		// 	and ars.to_stage_id = t_s.stage_id
		// 	and ac.article_stage_id = ars.article_stage_id
		// ";

		$query .= "
			union
			select 'N/A' as patient_name, ac.article_code as article_code, mp.procedure_name as procedure_name, concat(us.firstName, ' ', us.lastName) as pic_name, 
			t_s.stage_name as task_name, ars.status as status, ars.plan_date as plan_date, ars.actual_date as_actual_date, dr.doctor_name as doctor_name, 'N/A' as vn_no
			from article ac
			inner join medical_procedure mp on ac.procedure_id = mp.procedure_id
			inner join article_stage ars
			on ac.article_id = ars.article_id and ac.article_stage_id = ars.article_stage_id
			left join doctor dr on ac.doctor_id = dr.doctor_id
			inner join lportal.user_ us on ars.to_user_id = us.userId
			inner join stage t_s on ars.to_stage_id = t_s.stage_id
			where 1=1
		";

		empty($request->status) ?: ($query .= " and ars.status = ? " AND $qinput[] = $request->status);
		empty($request->procedure_id) ?: ($query .= " and ac.procedure_id = ? " AND $qinput[] = $request->procedure_id);
		empty($request->doctor_id) ?: ($query .= " and ac.doctor_id = ? " AND $qinput[] = $request->doctor_id);
		empty($request->user_id) ?: ($query .= " and ars.to_user_id  = ? " AND $qinput[] = $request->user_id);	

		$items = DB::select($query,$qinput);
		
		// Get the current page from the url if it's not set default to 1
		empty($request->page) ? $page = 1 : $page = $request->page;
		
		// Number of items per page
		empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
		
		$offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

		// Get only the items you need using array_slice (only get 10 items since that's what you need)
		$itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
		
		// Return the paginator with only 10 items but with the count of all items and set the it on the correct page
		$result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);				
		
		return response()->json($result);
		
	}
}