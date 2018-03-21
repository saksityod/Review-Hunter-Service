<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\User;
use App\UsageLog;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use JWTAuth;
use Auth;
use Validator;
use DB;
use Mail;
use Hash;
use Adldap;
use Exception;
use Tymon\JWTAuth\Exceptions\JWTException;
use Adldap\Exceptions\Auth\BindException;
//use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;


class AuthenticateController extends Controller
{

	public function __construct()
	{
		$this->middleware('jwt.auth', ['only' => ['index']]);
	}
   
    public function index(Request $request)
    {
		// try {
		// 	$config = SystemConfiguration::firstOrFail();
		// } catch (ModelNotFoundException $e) {
		// 	return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		// }		
		

		// $emp = DB::select("
		// 	select b.is_hr, b.is_self_assign, a.emp_code
		// 	from employee a
		// 	inner join appraisal_level b
		// 	on a.level_id = b.level_id
		// 	where a.emp_code = ?
		// ", array(Auth::id()));

		// $role = db::table('lportal.users_roles as ur')
  //               ->join('lportal.role_ as r' ,'ur.roleId' ,'=','r.roleId')
  //               ->where('ur.userId',$req->userId)
  //               ->select('r.roleId','r.name')
  //               ->get();
  //       $role_arr = [];
  //       foreach ($role as $r) { array_push($role_arr, $r->name);    }

  //       return $role_arr;

		$roles = DB::select("
			SELECT r.roleId,
					r.name
			FROM lportal.user_ u
			inner JOIN lportal.users_roles ur
			on ur.userId = u.userId
			inner join lportal.role_ r
			on r.roleId = ur.roleId
			where u.screenName = ?
		", array(Auth::id()));

		$user = DB::select("
			SELECT userId
			FROM lportal.user_
			where screenName = ?
		", array(Auth::id()));
		
		
		if (empty(Auth::id())) {
			return response()->json(['error' => 'User not found.'], 500);
		}

		return response()->json(['status' => 200, 'roles' => $roles, 'userID' => $user[0]->userId,'screenName'=>Auth::id()]);
    }    
	
	public function debug(Request $request)
	{
		// $a = [['a' => 1, 'b' => 2], ['a' => 2, 'b' => 1], ['a' => 1, 'b' => 2], ['a' => 3, 'b' => 2]];
		return 'done';
		// $a = array_unique($a,SORT_REGULAR);
		
		// return $a;
		// // Get array keys
		// $arrayKeys = array_keys($a);
		// // Fetch last array key
		// $lastArrayKey = array_pop($arrayKeys);
		// //iterate array
		// $string = '';
		// foreach($a as $k => $v) {
			// if($k == $lastArrayKey) {
				// //during array iteration this condition states the last element.
				// $string .= $v;
			// } else {
				// $string .= $v . ',';
			// }
		// }		
	//	$hash = Hash::make('secret');
		//return $hash;
		$mail_body = "
Hello from TYW KPI,

You have been appraised please click https://www.google.com

Best Regards,

From Going Jesse Team
		";
		$error = '';
		try {
		Mail::raw($mail_body, function($message)
		{
			$message->from('msuksang@gmail.com', 'TYW Team');

			$message->to('methee@goingjesse.com')->subject('You have been Appraised :-)');
		});
		} catch (Exception $e)
		{
			$error = $e->getMessage();
		}
		
		// Mail::later(5,'emails.welcome', array('msg' => $mail_body), function($message)
		// {
			// $message->from('msuksang@gmail.com', 'TYW Team');

			// $message->to('methee@goingjesse.com')->subject('You have been Appraised :-)');
		// });	
		
		return response()->json(['error' => $error]);
		
		
		//return response()->json($test);
	}

    public function authenticate(Request $request)
    {	    
		// try {
		// 	$config = SystemConfiguration::firstOrFail();
		// } catch (ModelNotFoundException $e) {
		// 	return response()->json(['status' => 404, 'data' => 'System Configuration not found in DB.']);
		// }			
        try {
            // verify the credentials and create a token for the user
            if (! $token = JWTAuth::attempt(array('screenName' => $request->username, 'password' => $request->password))) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        } catch (BindException $e) {
			return response()->json(['error' => 'Cannot connect to Server.'], 500);
		}
		
        // if no errors are encountered we can return a JWT
		
		// $emp = DB::select("
		// 	select b.is_hr
		// 	from employee a
		// 	inner join appraisal_level b
		// 	on a.level_id = b.level_id
		// 	where a.emp_code = ?
		// ", array($request->username));
		
		if (empty(Auth::id())) {
			return response()->json(['error' => 'User not found.'], 500);
		}
		
		/*$u = new UsageLog;
		$u->user_code = Auth::id();
		$u->user_plid = $request->plid;
		$u->save();	*/	
		
        return response()->json(['token' => $token]);
    }
	
	public function destroy()
	{
		$token = JWTAuth::getToken();
		if (empty($token)) {
			return response()->json(['status' => 401,'message' => 'no token provided']);
		} else {
			try
			{
				$response = JWTAuth::invalidate(JWTAuth::getToken());
				return response()->json(['status' => 200,'t_stat' => $response]);
				
			} catch (JWTException $e) {
				return response()->json(['status' => 401, 'message' => $e->getMessage()], $e->getStatusCode());
			}
			
		}
	}
}