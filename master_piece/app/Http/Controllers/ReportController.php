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

class ReportController extends Controller
{

	public function __construct()
	{
	  $this->middleware('jwt.auth');
	}

	// public function pagination(Request $request) {
	// 	$items = DB::select("
	// 		SELECT userId, screenName, uuid_
	// 		FROM lportal.user_
	// 	");
	// 	return response()->json($items);
	// }

	public function case_list_year(Request $request)
	{
		$items = DB::select("
			SELECT distinct(EXTRACT(YEAR FROM created_dttm)) 
			FROM patient_case
		");
		return response()->json($items);
	}

	public function case_list_month(Request $request)
	{
		$items = DB::select("
			SELECT distinct month(created_dttm),
				case
					when monthname(created_dttm) = 'January' then 'มกราคม'
					when monthname(created_dttm) = 'February' then 'กุมภาพันธ์'
					when monthname(created_dttm) = 'March' then 'มีนาคม'
					when monthname(created_dttm) = 'April' then 'เมษายน'
					when monthname(created_dttm) = 'May' then 'พฤษภาคม'
					when monthname(created_dttm) = 'June' then 'มิถุนายน'
					when monthname(created_dttm) = 'July' then 'กรกฎาคม'
					when monthname(created_dttm) = 'August' then 'สิงหาคม'
					when monthname(created_dttm) = 'September' then 'กันยายน'
					when monthname(created_dttm) = 'October' then 'ตุลาคม'
					when monthname(created_dttm) = 'November' then 'พฤศจิกายน'
					when monthname(created_dttm) = 'December' then 'ธันวาคม'
				end as monthname
			FROM patient_case
		");
		return response()->json($items);
	}

	public function writer_list_writer(Request $request)
	{
		$items = DB::select("
			SELECT u.userId, concat(u.firstName,' ',u.lastName) 
			FROM lportal.user_ u, lportal.users_roles ur, lportal.role_ r
			where ur.userId = u.userId
			and ur.roleId = r.roleId
			and r.roleId = 22307
		");
		return response()->json($items);
	}

	public function case_list_doctor(Request $request)
	{
		$items = DB::select("
			select doctor_id, doctor_name from doctor
			where is_active = 1
			order by doctor_name
		");
		return response()->json($items);
	}

	public function case_list_case_type(Request $request)
	{
		$items = DB::select("
			select case_type_id, case_type from case_type
			where is_active = 1
		");
		return response()->json($items);
	}

	public function case_followup_year(Request $request)
	{
		$items = DB::select("
			SELECT distinct followup_year FROM case_followup
		");
		return response()->json($items);
	}

	public function case_followup_case_type(Request $request)
	{
		$items = DB::select("
			select case_type_id, case_type from case_type
			where is_active = 1
			order by case_type
		");
		return response()->json($items);
	}

	public function case_followup_case_group(Request $request)
	{
		$items = DB::select("
			select case_group_id, case_group
			from case_group
			where is_active = 1
			order by case_group
		");
		return response()->json($items);
	}

	public function list_selector_time(Request $request)
	{
		if($request->time==7) {
			$date = date('Y-m-d', strtotime('-7 days'));
		} else if($request->time==15) {
			$date = date('Y-m-d', strtotime('-15 days'));
		} else if($request->time==30) {
			$date = date('Y-m-d', strtotime('-30 days'));
		} else if($request->time==365) {
			$date = date('Y-m-d', strtotime('-365 days'));
		}

		return response()->json(['status' => 200, 'data' => $date]);
	}
}
