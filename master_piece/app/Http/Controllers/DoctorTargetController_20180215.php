<?php

namespace App\Http\Controllers;

use App\Doctor;
use App\DoctorTarget;
use App\MedicalProcedure;

use Illuminate\Http\Request;
use DB;
use File;
use Auth;
use Excel;
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
    public function get_current_date(){
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

    public function list_doctor_all(Request $req) {
        $doctor_name = $req->doctor_name;
        $data = Doctor::where('doctor_name', 'like','%'.$doctor_name.'%')->where('is_active',1)->get();
        return response()->json($data);
    }

    public function destroy_one(Request $req){
        try {
            $id = $req->id;
            if(DoctorTarget::where('doctor_target_id',$id)->delete()){
                return response()->json(['status' => 200, 'data' => $id]);
            }else{
                return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
            }
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
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
            SELECT year 
            from doctor_target
            group by year
        ");
        return response()->json($items);
    }

    public function list_case() {
        $items = DB::select("
            SELECT case_type_id,case_type 
            from case_type
        ");
        return response()->json($items);
    }

    public function search_target(Request $req) {
        $doctorName = $req->doctorName;
        $procedure = $req->procedure;
        $year = $req->year;
        $perpage = $req->perpage?$req->perpage:10;
        $data = DoctorTarget::with([ 'doctor','doctorProcedure.medical_procedure'])
                ->whereHas('doctor',function($qry) use($doctorName){
                        $qry->where('doctor_name', 'like','%'.$doctorName.'%');
                    })
                ->whereHas('doctorProcedure',function($qry) use($procedure){
                        $qry->where('procedure_id',$procedure); 
                    })
                ->where('year',$year)
                ->paginate($perpage);
        return response()->json($data);
    }

    public function get_target(Request $request) {

        try {
            $id = $request->doctor_target_id;
            $data = DoctorTarget::where('doctor_target_id',$id)->with(['doctor','caseType'])->first();
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
        $errors = [];
        DB::beginTransaction();
        $validator = Validator::make($request->formdata, [
            'doctor_id' => 'required|integer','case_type_id' => 'required|integer',
            'procedure_id' => 'required|integer','year' => 'required|integer',
            'target_month1' => 'required|integer','target_month2' => 'required|integer',
            'target_month3' => 'required|integer','target_month4' => 'required|integer',
            'target_month5' => 'required|integer','target_month6' => 'required|integer',
            'target_month7' => 'required|integer','target_month8' => 'required|integer',
            'target_month9' => 'required|integer','target_month10' => 'required|integer',
            'target_month11' => 'required|integer','target_month12' => 'required|integer',
        ]);

        if($validator->fails()){$errors[] = $validator->errors();}

        if(empty($request->formdata['doctor_target_id'])) {
            //add target
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

            try {
                $doctor_target->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "doctor_target",
                    "errors" => substr($e,0,254)
                ];
            }
        }else {

            //update target
            try {
                DoctorTarget::findOrFail($request->formdata['doctor_target_id']);
            }
            catch (ModelNotFoundException $e) {
                $errors[] = [
                    "status" => 404,
                    "data" => "DoctorTarget not found.",
                    "errors" => substr($e,0,254)
                ];
            }

            $doctor_target = DoctorTarget::find($request->formdata['doctor_target_id']);
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
                    "doctor_target_id" => $request->formdata['doctor_target_id'],
                    "errors" => substr($e,0,254)
                ];
            }
        }
        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }
}