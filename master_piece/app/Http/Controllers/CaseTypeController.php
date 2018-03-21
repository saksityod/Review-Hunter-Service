<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\CaseType;
use Auth;
use DB;
use Validator;
use Exception;

class CaseTypeController extends Controller {

    public function __construct() {
        $this->middleware('jwt.auth');
    }

    public function get_all(Request $req) {
        $perpage = $req->perpage?$req->perpage:10;
        $data = DB::table('case_type')->orderBy('case_type','ASC')->paginate($perpage);
        return $data;
    }

    public function get_one($id) {  
        try {
            $data = CaseType::findOrFail($id);
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
                'caseTypeName' => 'required',
        ],[
            'caseTypeName.required' => 'กรุณากรอก ชื่อ.',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        $caseType = new CaseType();
        $caseType->case_type     = $req->caseTypeName;
        $caseType->is_active     = $req->is_active;
        $caseType->created_by    = Auth::id();
        $caseType->updated_by    = Auth::id();
        try {
            $caseType->save();
        } catch (Exception $e) {
            $errors[] = [
                "table_name" => "case_type",
                "errors" => $e
            ];
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }

    public function update($id,Request $req) {
        //return response()->json(['status' => $status, 'errors' => $errors]);
        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator = Validator::make($req->all(), [
                'caseTypeName' => 'required',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        $caseType = CaseType::find($id);
        $caseType->case_type     = $req->caseTypeName;
        $caseType->is_active     = $req->is_active;
        $caseType->updated_by    = Auth::id();
        try {
            $caseType->save();
        } catch (Exception $e) {
            $errors[] = [
                "table_name" => "case_type",
                "errors" => $e
            ];
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }

    public function destroy($id) {

        try {
            $item = CaseType::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'CaseType not found.']);
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