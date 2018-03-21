<?php

namespace App\Http\Controllers;

use App\Doctor;
use App\DoctorProcedure;
use App\DoctorEducation;
use App\DoctorWork;
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

class DoctorProfileController extends Controller {
    
    public function __construct() {
        $this->middleware('jwt.auth');
    }
    public function get_current_date(){
        return response()->json(date('d-m-Y'));
    }
    public function list_doctor(Request $req) {
        $procedure = $req->procedure;
        $perpage = $req->perpage?$req->perpage:10;
        $myQry = Doctor::query();

        $myQry = $myQry->with([  'doctor_procedure.medical_procedure','doctor_procedure',
                    'doctor_education'=> function($qry){
                         $qry->where('doctor_education.is_use','=', 1);
                    }
                ]);
        if($req->procedure){
            $myQry = $myQry->wherehas('doctor_procedure',function($qry) use($procedure){
                        $qry->where('procedure_id',$procedure);
                    });
        }
        $data = $myQry->where('doctor_name', 'like','%'.$req->search.'%')
                ->where('is_active',1)
                ->orderBy('doctor_id','DESC')->paginate($perpage);
        return response()->json($data);
    }

    public function getProcedure(){
        $data = MedicalProcedure::where('is_active',1)->get();
    }

    public function list_medical_procedure() {
        $data = MedicalProcedure::where('is_active',1)->get();
        return $data;
    }

    public function get_doctor(Request $req) {
        try {
            $data = Doctor::where('doctor_id',$req->doctor_id)
                ->with([  'doctor_procedure.medical_procedure','doctor_work','doctor_procedure','doctor_education'
                ])->first();
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Doctor not found.']);
        }
        return response()->json(['status' => 200, 'data' => $data]);
    }
    public function destroy_edu(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                DoctorEducation::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }
    public function destroy_work(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $work) {
                DoctorWork::find($work)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }
     public function destroy_doctor(Request $req){
        try {
            $id = $req->id;
            if(Doctor::where('doctor_id',$id)->update(['is_active'=>0])){
                return response()->json(['status' => 200, 'data' => $id]);
            }else{
                return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
            }
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }
    

    public function crud(Request $request) {
        $errors = [];
        DB::beginTransaction();

        if(!empty($request->formdata['doctor'])) {
            $validator_doctor = Validator::make($request->formdata['doctor'], [
                    'doctor_name' => 'required|max:255',
                    'gender' => 'required|max:10',
                    'expertise' => 'required|max:100',
                    'is_active' => 'boolean',
            ]);

            foreach($request->formdata['doctor_procedure'] as $row) {
                $validator_doctor_procedure = Validator::make($row, ['procedure_id' => 'required|integer',]);
            }

            if($validator_doctor->fails()){$errors[] = $validator_doctor->errors();}
            if($validator_doctor_procedure->fails()){$errors[] = $validator_doctor_procedure->errors();}

            if(empty($request->formdata['doctor']['doctor_id'])) {
                //add doctor
                $doctor = new Doctor;
                $doctor->doctor_name = $request->formdata['doctor']['doctor_name'];
                $doctor->gender = $request->formdata['doctor']['gender'];
                $doctor->expertise = $request->formdata['doctor']['expertise'];
                $doctor->is_active = $request->formdata['doctor']['is_active'];
                $doctor->created_by = Auth::id();
                $doctor->updated_by = Auth::id();
                try {
                    $doctor->save();
                    $current_doctor_id = $doctor->doctor_id;
                } catch (Exception $e) {
                    $errors[] = [
                        "table_name" => "doctor",
                        "errors" => substr($e,0,254)
                    ];
                }

                foreach($request->formdata['doctor_procedure'] as $procedure) {
                    $doctor_procedure = new DoctorProcedure;
                    $doctor_procedure->doctor_id = $current_doctor_id;
                    $doctor_procedure->procedure_id = $procedure['procedure_id'];
                    $doctor_procedure->created_by = Auth::id();
                    try {
                        $doctor_procedure->save();
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "doctor_procedure",
                            "doctor_id" => $current_doctor_id,
                            "errors" => substr($e,0,254)
                        ];
                    }
                }

                foreach($request->formdata['doctor_education'] as $education) {
                    $doctor_education = new DoctorEducation;
                    $doctor_education->doctor_id = $current_doctor_id;
                    $doctor_education->education_institution = $education['education_institution'];
                    $doctor_education->education_level = $education['education_level'];
                    $doctor_education->education_degree = $education['education_degree'];
                    $doctor_education->is_use = $education['is_use'];
                    $doctor_education->created_by = Auth::id();
                    $doctor_education->updated_by = Auth::id();
                    try {
                        $doctor_education->save();
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "doctor_education",
                            "doctor_id" => $current_doctor_id,
                            "errors" => substr($e,0,254)
                        ];
                    }
                }

                foreach($request->formdata['doctor_work'] as $work) {
                    $doctor_work = new DoctorWork;
                    $doctor_work->doctor_id = $current_doctor_id;
                    $doctor_work->start_year = $work['start_year'];
                    $doctor_work->end_year = $work['end_year'];
                    $doctor_work->company_name = $work['company_name'];
                    $doctor_work->created_by = Auth::id();
                    $doctor_work->updated_by = Auth::id();
                    try {
                        $doctor_work->save();
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "doctor_work",
                            "doctor_id" => $current_doctor_id,
                            "errors" => substr($e,0,254)
                        ];
                    }
                }
            } else {
                //update doctor
                $doctor_id = $request->formdata['doctor']['doctor_id'];
                try {
                    Doctor::findOrFail($doctor_id);
                }
                catch (ModelNotFoundException $e) {
                    $errors[] = [
                        "status" => 404,
                        "data" => "Doctor not found.",
                        "errors" => substr($e,0,254)
                    ];
                }

                $doctor = Doctor::find($doctor_id);
                $doctor->doctor_name = $request->formdata['doctor']['doctor_name'];
                $doctor->gender = $request->formdata['doctor']['gender'];
                $doctor->expertise = $request->formdata['doctor']['expertise'];
                $doctor->is_active = $request->formdata['doctor']['is_active'];
                $doctor->updated_by = Auth::id();
                try {
                    $doctor->save();
                } catch (Exception $e) {
                    $errors[] = [
                        "table_name" => "doctor",
                        "doctor_id" => $doctor_id,
                        "errors" => substr($e,0,254)
                    ];
                }

                $doctor_procedure = DoctorProcedure::where('doctor_id', '=', $doctor_id)->delete();
                foreach($request->formdata['doctor_procedure'] as $procedure) {
                    $doctor_procedure = new DoctorProcedure;
                    $doctor_procedure->doctor_id = $doctor_id;
                    $doctor_procedure->procedure_id = $procedure['procedure_id'];
                    $doctor_procedure->created_by = Auth::id();
                    try {
                        $doctor_procedure->save();
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "doctor_procedure",
                            "doctor_id" => $doctor_id,
                            "errors" => substr($e,0,254)
                        ];
                    }
                }

                if(!empty($request->formdata['doctor_education'])) {
                    foreach($request->formdata['doctor_education'] as $education) {
                        if($education['doctor_education_id']){
                            $doctor_education = DoctorEducation::find($education['doctor_education_id']);
                            $doctor_education->education_institution = $education['education_institution'];
                            $doctor_education->education_level = $education['education_level'];
                            $doctor_education->education_degree = $education['education_degree'];
                            $doctor_education->is_use = $education['is_use'];
                            $doctor_education->updated_by = Auth::id();
                            try {
                                $doctor_education->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "doctor_education",
                                    "doctor_education_id" => $education['doctor_education_id'],
                                    "errors" => substr($e,0,254)
                                ];
                            }
                        } else {
                            $edu = new DoctorEducation();
                            $edu->doctor_id =  $doctor_id;
                            $edu->education_institution = $education['education_institution'];
                            $edu->education_level = $education['education_level'];
                            $edu->education_degree = $education['education_degree'];
                            $edu->created_by = Auth::id();
                            $edu->updated_by = Auth::id();
                            $edu->is_use = $education['is_use'];
                            $edu->save();
                        }
                    }
                }

                if(!empty($request->formdata['doctor_work'])){
                    foreach($request->formdata['doctor_work'] as $work) {
                        if($work['doctor_work_id']){
                            $doctor_work = DoctorWork::find($work['doctor_work_id']);
                            $doctor_work->start_year = $work['start_year'];
                            $doctor_work->end_year = $work['end_year'];
                            $doctor_work->company_name = $work['company_name'];
                            $doctor_work->updated_by = Auth::id();
                            try {
                                $doctor_work->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "doctor_work",
                                    "doctor_work_id" => $work['doctor_work_id'],
                                    "errors" => substr($e,0,254)
                                ];
                            }
                        }else{
                            $wk = new DoctorWork();
                            $wk->doctor_id      = $doctor_id;
                            $wk->start_year     = $work['start_year'];
                            $wk->end_year       = $work['end_year'];
                            $wk->company_name   = $work['company_name'];
                            $wk->created_by     = Auth::id();
                            $wk->updated_by     = Auth::id();
                            $wk->save();

                        }
                    }
                }
            }
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }
}