<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\MedicalProcedure;
use DB;
use Auth;
use Validator;
use Exception;

class MedicalProcedureController extends Controller {

    public function __construct() {
        $this->middleware('jwt.auth');
    }

    public function get_all(Request $req) {
        $perpage = $req->perpage?$req->perpage:10;
        $data = DB::table('medical_procedure')->orderBy('procedure_name','ASC')->paginate($perpage);
        return $data;
    }

    public function get_one($id) {
        try {
            $data = MedicalProcedure::findOrFail($id);
            return response()->json(['status' => 200,'data'=>$data, 'message' => 'ค้นหาข้อมูลสำเร็จ.']);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'message' => 'เกิดข้อผิดพลาด.']);
        }
    }

    public function create(Request $req) {
        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator = Validator::make($req->all(), [
                'procedure_name' => 'required',
        ],[
            'procedure_name.required' => 'กรุณากรอก ชื่อ.',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        $mProcedure = new MedicalProcedure();
        $mProcedure->procedure_name     = $req->procedure_name;
        $mProcedure->is_active          = $req->is_active;
        $mProcedure->created_by         = Auth::id();
        $mProcedure->updated_by         = Auth::id();
        try {
            $mProcedure->save();
        } catch (Exception $e) {
            $errors[] = [
                "table_name" => "medical_procedure",
                "errors" => $e
            ];
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }

    public function update($id,Request $req) {
        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator = Validator::make($req->all(), [
                'procedure_name' => 'required',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        $mProcedure = MedicalProcedure::find($id);
        $mProcedure->procedure_name     = $req->procedure_name;
        $mProcedure->is_active          = $req->is_active;
        $mProcedure->created_by         = Auth::id();
        $mProcedure->updated_by         = Auth::id();
        try {
            $mProcedure->save();
        } catch (Exception $e) {
            $errors[] = [
                "table_name" => "medical_procedure",
                "errors" => $e
            ];
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);

    }


    public function destroy($id) {
        // try {
        //     $mProcedure = MedicalProcedure::find($id);
        //     if(count($mProcedure->doctorTarget) == 0 && count($mProcedure->doctorProcedure) == 0 ){
        //         if($mProcedure->delete()){
        //             return response()->json(['status' => 200, 'message' => 'Destroy Success.']); 
        //         }
        //     }
        //     return response()->json(['status' => 400, 'message' => 'Can\'t Delete This Procedure. This Procedure has Relation.']);
        // }catch (ModelNotFoundException $e) {
        //     return response()->json(['status' => 404,'message' => 'Procedure not found.']);
        // }

        try {
            $item = MedicalProcedure::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'MedicalProcedure not found.']);
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

   


}