<?php

namespace App\Http\Controllers;

use App\Doctor;
use App\DoctorTarget;
use App\MedicalProcedure;
use App\DoctorTargetAlert;
use App\User;

use Illuminate\Http\Request;
use DB;
use File;
use Auth;
use Excel;
use Mail;
use Response;
use Validator;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DoctorTargetController extends Controller {

    public function __construct() {
        $this->middleware('jwt.auth');
    }
    public function get_current_date() {
        return response()->json(date('d-m-Y'));
    }

    public function list_doctor(Request $request) {
        $items = DB::select("
            SELECT d.doctor_name,d.doctor_id
            from doctor d
            inner join doctor_target dt
            on d.doctor_id = dt.doctor_id
            where d.doctor_name like '%{$request->doctor_name}%'
            and d.is_active = 1
            group by d.doctor_id
        ");
        return response()->json($items);
    }

    public function get_user(Request $req) {
        $items = DB::select("
            SELECT s.userId, s.emailAddress, s.screenName
            FROM lportal.user_ s, lportal.users_roles ur, lportal.role_ r 
            where s.userId = ur.userId 
            and ur.roleId = r.roleId 
            and r.roleId in (22301,22302,22303,22304,22305,22306,22307,22308,22309,22310,22311,22312,22313)
            group by s.userId
            order by s.screenName ASC
        ");
        return response()->json($items);
    }

    public function list_doctor_all(Request $req) {
        $doctor_name = $req->doctor_name;
        $data = Doctor::where('doctor_name', 'like','%'.$doctor_name.'%')->where('is_active',1)->get();
        return response()->json($data);
    }

    public function destroy_one(Request $req){
        // try {
        //     $id = $req->id;
        //     $model = DoctorTarget::find($id);
        //             // return response()->json($model->DoctorTargetAlert);

        //     if(!$model->DoctorTargetAlert){
        //         if($model->delete()) {
        //             return response()->json(['status' => 200, 'data' => $id]);
        //         }
        //     } else {
        //         return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        //     }
        // } catch (ModelNotFoundException $e) {
        //     return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        // }

        try {
            $item = DoctorTarget::findOrFail($req->id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'DoctorTarget not found.']);
        }   

        try {
            $item->delete();
        } catch (Exception $e) {
            if ($e->errorInfo[1] == 1451) {
                return response()->json(['status' => 400, 'data' => 'ไม่สามารถลบได้ เนื่องจากมีการใช้งานอยู่']);
            } else {
                return response()->json($e->errorInfo);
            }
        }

        return response()->json(['status' => 200]);
    }
    public function list_medical_procedure(Request $request) {
        // return $request->all();
        $case_type_sql = $request->case_type_id?' where dt.case_type_id = '.$request->case_type_id:'';
        $items = DB::select("
            SELECT mp.procedure_id,mp.procedure_name
            from medical_procedure mp
            inner join doctor_target dt
            on mp.procedure_id = dt.procedure_id
            ".$case_type_sql." 
            group by mp.procedure_id
        ");
        return response()->json($items);
    }

    public function list_medical_procedure_all(Request $request) {
        $data = MedicalProcedure::where('is_active',1)->get();
        return response()->json($data);
    }

    public function list_year() {
        $items = DB::select("
            SELECT year, year + 543 as year_format
            from doctor_target
            group by year
        ");
        return response()->json($items);
    }

    public function list_case() {
        $items = DB::select("
            SELECT case_type_id,case_type 
            from case_type
            where is_active = 1
        ");
        return response()->json($items);
    }

    public function search_target(Request $req) {
        $doctorName = $req->doctorName;
        $procedure = $req->procedure;
        $year = $req->year;
        $perpage = $req->perpage?$req->perpage:10;
        // $data = DoctorTarget::with([ 'doctor','doctorProcedure.medical_procedure'])
        //         ->whereHas('doctor',function($qry) use($doctorName){
        //                 $qry->where('doctor_name', 'like','%'.$doctorName.'%');
        //             })
        //         ->whereHas('doctorProcedure',function($qry) use($procedure){
        //                 $qry->where('procedure_id',$procedure); 
        //             })
        //         ->where('year',$year)
        //         ->paginate($perpage);
        $myQry = DoctorTarget::query();
        $myQry->with(['doctor'])
                ->whereHas('doctor',function($qry) use($doctorName){
                        $qry->where('doctor_name', 'like','%'.$doctorName.'%');
                    });

        if(empty($procedure)) {
            $myQry->with(['medicalProcedure']);
        } else {
            $myQry->with(['medicalProcedure'])
            ->whereHas('medicalProcedure',function($qry) use($procedure){
                $qry->where('procedure_id',$procedure); 
            });
        }

        if($year)  $myQry->where('year',$year);

        // if($procedure) {
        //     $myQry->whereHas('doctorProcedure',function($qry) use($procedure){
        //         $qry->where('procedure_id',$procedure); 
        //     });
        // }

        $data = $myQry->paginate($perpage);
        //print_r($data);
        return response()->json($data);

    }

    // public function search_target(Request $req){
    //     $search     = $req->search;
    //     $caseType   = $req->caseType;
    //     $procedure  = $req->procedure;
    //     $social     = $req->social;
    //     $hn         = $req->hn;
    //     $review     = $req->review;
    //     $expDate    = $req->expDate;

    //     $perpage = $req->perpage?$req->perpage:100;
    //     $myQry = PatientCase::query();
    //     $myQry = $myQry->with(['patient','patientSocialMedia.socialMedia','procedure','caseType','caseGroup']);
    //     if($caseType){      $myQry = $myQry->where('case_type_id',$caseType);   }
    //     if($procedure){     $myQry = $myQry->where('procedure_id',$procedure);   }
    //     if($review){   
    //         if($review==1)  $myQry = $myQry->where('is_bad_case',1);   
    //         if($review==2)  $myQry = $myQry->where('is_good_review',1);   
    //         if($review==3)  $myQry = $myQry->where('is_good_case',1);   
    //         if($review==4)  $myQry = $myQry->where('is_good_case',0)->where('is_good_review',0)->where('is_bad_case',0);  
    //     }
    //     if($social){  
    //         $data = $myQry->wherehas('patientSocialMedia',function($qry) use($social){
    //                     $qry->where('social_media_id',$social);     });     }
    //     if($search){  
    //         $data = $myQry->wherehas('patient',function($qry) use($search){
    //                     $qry->where('patient_name', 'like','%'.$search.'%');    });     }
    //     if($hn){  
    //         $data = $myQry->wherehas('patient',function($qry) use($hn){
    //                     $qry->where('hn_no', 'like','%'.$hn.'%');       });     }
    //     $data = $myQry->paginate($perpage);
    //     return response()->json($data);
    // }

    public function get_target(Request $request) {

        try {
            $id = $request->doctor_target_id;
            $data = DoctorTarget::where('doctor_target_id',$id)->with(['doctor','caseType','doctorProcedure.medical_procedure','caseType'])->first();
        }
        catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'DoctorTarget not found.']);
        }
        return response()->json(['status' => 200, 'data' => $data]);

        // $items = DB::select(
        //     "SELECT
        //         dt.year,
        //         dt.target_month1,
        //         dt.target_month2,
        //         dt.target_month3,
        //         dt.target_month4,
        //         dt.target_month5,
        //         dt.target_month6,
        //         dt.target_month7,
        //         dt.target_month8,
        //         dt.target_month9,
        //         dt.target_month10,
        //         dt.target_month11,
        //         dt.target_month12,
        //         d.doctor_id,
        //         d.doctor_name,
        //         ct.case_type_id,
        //         ct.case_type,
        //         mp.procedure_id,
        //         mp.procedure_name
        //     from doctor_target dt
        //     INNER JOIN doctor d ON dt.doctor_id = d.doctor_id
        //     INNER JOIN medical_procedure mp ON dt.procedure_id = mp.procedure_id
        //     INNER JOIN case_type ct ON dt.case_type_id = ct.case_type_id
        //     where dt.doctor_target_id = '{$request->doctor_target_id}'
        // ");
        // return response()->json(['status' => 200, 'data' => $items]);
    }

    public function cru(Request $request) {

       //return response()->json(['status' => 400, 'errors' => $request->all()]);

        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator = Validator::make($request->formdata, [
                'doctor_id' => 'required|integer',
                'case_type_id' => 'required|integer',
                'procedure_id' => 'required|integer',
                'year' => 'required|integer',
                'target_month1' => 'required|integer',
                'target_month2' => 'required|integer',
                'target_month3' => 'required|integer',
                'target_month4' => 'required|integer',
                'target_month5' => 'required|integer',
                'target_month6' => 'required|integer',
                'target_month7' => 'required|integer',
                'target_month8' => 'required|integer',
                'target_month9' => 'required|integer',
                'target_month10' => 'required|integer',
                'target_month11' => 'required|integer',
                'target_month12' => 'required|integer',
                'alert' => 'required',
            ],
            [
                'doctor_id.required' => 'กรุณากรอก ชื่อแพทย์.',
                'doctor_id.integer'  => 'ไม่พบข้อมูล ชื่อแพทย์.',
                'case_type_id.required' => 'กรุณากรอก ประเภท Case.',
                'case_type_id.integer'  => 'ไม่พบข้อมูล ประเภท Case.',
                'procedure_id.required' => 'กรุณากรอก หัตถการ.',
                'procedure_id.integer'  => 'ไม่พบข้อมูล หัตถการ.',
                'year.required' => 'กรุณากรอก ปี.',
                'year.integer'  => 'ไม่พบข้อมูล ปี.',
                'target_month1.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนมกราคม.',
                'target_month1.integer'  => 'เป้าจำนวน Case ของเดือนมกราคมต้องเป็นตัวเลข.',
                'target_month2.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนกุมภาพันธ์.',
                'target_month2.integer'  => 'เป้าจำนวน Case ของเดือนกุมภาพันธ์ต้องเป็นตัวเลข.',
                'target_month3.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนมีนาคม.',
                'target_month3.integer'  => 'เป้าจำนวน Case ของเดือนมีนาคมต้องเป็นตัวเลข.',
                'target_month4.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนเมษายน.',
                'target_month4.integer'  => 'เป้าจำนวน Case ของเดือนเมษายนต้องเป็นตัวเลข.',
                'target_month5.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนพฤษภาคม.',
                'target_month5.integer'  => 'เป้าจำนวน Case ของเดือนพฤษภาคมต้องเป็นตัวเลข.',
                'target_month6.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนมิถุนายน.',
                'target_month6.integer'  => 'เป้าจำนวน Case ของเดือนมิถุนายนต้องเป็นตัวเลข.',
                'target_month7.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนกรกฎาคม.',
                'target_month7.integer'  => 'เป้าจำนวน Case ของเดือนกรกฎาคมต้องเป็นตัวเลข.',
                'target_month8.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนสิงหาคม.',
                'target_month8.integer'  => 'เป้าจำนวน Case ของเดือนสิงหาคมต้องเป็นตัวเลข.',
                'target_month9.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนกันยายน.',
                'target_month9.integer'  => 'เป้าจำนวน Case ของเดือนกันยายนต้องเป็นตัวเลข.',
                'target_month10.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนตุลาคม.',
                'target_month10.integer'  => 'เป้าจำนวน Case ของเดือนตุลาคมต้องเป็นตัวเลข.',
                'target_month11.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนพฤศจิกายน.',
                'target_month11.integer'  => 'เป้าจำนวน Case ของเดือนพฤศจิกายนต้องเป็นตัวเลข.',
                'target_month12.required' => 'กรุณากรอก เป้าจำนวน Case ของเดือนธันวาคม.',
                'target_month12.integer'  => 'เป้าจำนวน Case ของเดือนธันวาคมต้องเป็นตัวเลข.',
                'alert.required' => 'กรุณาเลือก แจ้งเตือน.',
            ]
        );

        if($validator->fails()) {
            $errors_validator[] = $validator->errors();
        }

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        if(empty($request->formdata['doctor_target_id'])) {
            //add target

            if($request->check_cu['confirm_check_target']==null) {

                $items = DB::select("
                    SELECT doctor_target_id
                    from doctor_target
                    where doctor_id = '".$request->formdata['doctor_id']."'
                    and case_type_id = '".$request->formdata['case_type_id']."'
                    and year = '".$request->formdata['year']."'
                ");

                if(!empty($items)) {
                    return response()->json(['status' => 205, 'data' => $items[0]->doctor_target_id]);
                }
            }

            if(!empty($request->check_cu['target_id_confirm'])) {
                $doctor_target = DoctorTarget::find($request->check_cu['target_id_confirm']);
                $doctor_target->doctor_id = $request->formdata['doctor_id'];
                $doctor_target->case_type_id = $request->formdata['case_type_id'];
                $doctor_target->procedure_id = $request->formdata['procedure_id'];
                $doctor_target->year = $request->formdata['year'];
                $doctor_target->target_month1 = $request->formdata['target_month1'];
                $doctor_target->target_month2 = $request->formdata['target_month2'];
                $doctor_target->target_month3 = $request->formdata['target_month3'];
                $doctor_target->target_month4 = $request->formdata['target_month4'];
                $doctor_target->target_month5 = $request->formdata['target_month5'];
                $doctor_target->target_month6 = $request->formdata['target_month6'];
                $doctor_target->target_month7 = $request->formdata['target_month7'];
                $doctor_target->target_month8 = $request->formdata['target_month8'];
                $doctor_target->target_month9 = $request->formdata['target_month9'];
                $doctor_target->target_month10 = $request->formdata['target_month10'];
                $doctor_target->target_month11 = $request->formdata['target_month11'];
                $doctor_target->target_month12 = $request->formdata['target_month12'];
                $doctor_target->updated_by = Auth::id();
            } else {
                $doctor_target = new DoctorTarget;
                $doctor_target->doctor_id = $request->formdata['doctor_id'];
                $doctor_target->case_type_id = $request->formdata['case_type_id'];
                $doctor_target->procedure_id = $request->formdata['procedure_id'];
                $doctor_target->year = $request->formdata['year'];
                $doctor_target->target_month1 = $request->formdata['target_month1'];
                $doctor_target->target_month2 = $request->formdata['target_month2'];
                $doctor_target->target_month3 = $request->formdata['target_month3'];
                $doctor_target->target_month4 = $request->formdata['target_month4'];
                $doctor_target->target_month5 = $request->formdata['target_month5'];
                $doctor_target->target_month6 = $request->formdata['target_month6'];
                $doctor_target->target_month7 = $request->formdata['target_month7'];
                $doctor_target->target_month8 = $request->formdata['target_month8'];
                $doctor_target->target_month9 = $request->formdata['target_month9'];
                $doctor_target->target_month10 = $request->formdata['target_month10'];
                $doctor_target->target_month11 = $request->formdata['target_month11'];
                $doctor_target->target_month12 = $request->formdata['target_month12'];
                $doctor_target->created_by = Auth::id();
                $doctor_target->updated_by = Auth::id();
            }

            try {
                $doctor_target->save();
                $target_id = $doctor_target->doctor_target_id;
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "doctor_target",
                    "errors" => $e
                ];
            }
        } else {

            //update target
            try {
                DoctorTarget::findOrFail($request->formdata['doctor_target_id']);
            }
            catch (ModelNotFoundException $e) {
                $errors[] = [
                    "status" => 404,
                    "data" => "DoctorTarget not found.",
                    "errors" => $e
                ];
            }

            $target_id = $request->formdata['doctor_target_id'];
            $doctor_target = DoctorTarget::find($target_id);
            $doctor_target->doctor_id = $request->formdata['doctor_id'];
            $doctor_target->case_type_id = $request->formdata['case_type_id'];
            $doctor_target->procedure_id = $request->formdata['procedure_id'];
            $doctor_target->year = $request->formdata['year'];
            $doctor_target->target_month1 = $request->formdata['target_month1'];
            $doctor_target->target_month2 = $request->formdata['target_month2'];
            $doctor_target->target_month3 = $request->formdata['target_month3'];
            $doctor_target->target_month4 = $request->formdata['target_month4'];
            $doctor_target->target_month5 = $request->formdata['target_month5'];
            $doctor_target->target_month6 = $request->formdata['target_month6'];
            $doctor_target->target_month7 = $request->formdata['target_month7'];
            $doctor_target->target_month8 = $request->formdata['target_month8'];
            $doctor_target->target_month9 = $request->formdata['target_month9'];
            $doctor_target->target_month10 = $request->formdata['target_month10'];
            $doctor_target->target_month11 = $request->formdata['target_month11'];
            $doctor_target->target_month12 = $request->formdata['target_month12'];
            $doctor_target->updated_by = Auth::id();
            try {
                $doctor_target->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "doctor_target",
                    "doctor_target_id" => $target_id,
                    "errors" => $e
                ];
            }
        }

        if(empty($errors)) {

             $email_body = DB::select("
                SELECT p.procedure_name,d.doctor_name,dt.year
                from doctor_target dt
                inner join medical_procedure p on p.procedure_id = dt.procedure_id
                inner join doctor d on d.doctor_id = dt.doctor_id
                where dt.doctor_target_id = {$target_id}
            ");
            //send mail
            //return response()->json(['status' => 400, 'errors' => $request->alert]);
            // $errors_mail = $this->alert($target_id,$request->alert);
            // if(!empty($errors_mail)) {
            //     $errors[] = ["errors_mail" => $errors_mail];
            // }
            //$error = array();
            $to = array();
            try {
                $data = [
                    "doctor_name" => $email_body[0]->doctor_name,
                    "year" => $email_body[0]->year,
                    "procedure" => $email_body[0]->procedure_name,
                ];
                
                $from = 'gjtestmail2017@gmail.com';
                
                foreach ($request->formdata['alert'] as $a) {
                    $input_mail = explode('|', $a);
                    // return response()->json($input_mail);
                    $to[] = $input_mail[1];
                    $target_alert = new DoctorTargetAlert;
                    $target_alert->doctor_target_id = $target_id;
                    $target_alert->user_id = $input_mail[0];
                    $target_alert->created_by = Auth::id();
                    $target_alert->save();
                }
                
                Mail::send('emails.doctor_target', $data, function($message) use ($from, $to) {
                    $message->from($from, 'Review Hunter');
                    $message->to($to)->subject('แจ้งเตือน ระบบ Review Hunter มีการตั้งเป้าหมายใหม่');
                });

            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }

        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }

    // public function alert($target_id,$alert) {

    //     $error = array();
    //     $to = array();
    //     try {
    //         $data = ["chief_emp_name" => "the boss", "emp_name" => "the bae", "status" => "excellent"];
            
    //         $from = 'gjtestmail2017@gmail.com';
            
    //         foreach ($alert as $a) {
    //             foreach($a['email'] as $e) {
    //                 $to[] = $e;
    //             }
    //             $target_alert = new DoctorTargetAlert;
    //             $target_alert->doctor_target_id = $target_id;
    //             $target_alert->user_id = $a['user_id'];
    //             $target_alert->created_by = Auth::id();
    //             $target_alert->save();
    //         }
            
    //         Mail::send('emails.status', $data, function($message) use ($from, $to) {
    //             $message->from($from, 'Review Hunter');
    //             $message->to($to)->subject('Alert!');
    //         });

    //     } catch (Exception $e) {
    //         $error = $e->getMessage();
    //     }

    //     return $error;
    // }
}