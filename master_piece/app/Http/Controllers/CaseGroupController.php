<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use DB;
use App\CaseGroup;
use Validator;
use Exception;

class CaseGroupController extends Controller {

    public function __construct() {
        $this->middleware('jwt.auth');
    }

    public function get_all(Request $req) {
        $perpage = $req->perpage?$req->perpage:10;
        $data = DB::table('case_group')->orderBy('case_group','ASC')->paginate($perpage);
        return $data;
    }

    public function get_one(Request $req) {  
        try {
            $data = CaseGroup::findOrFail($req->id);
            return response()->json(['status' => 200,'data'=>$data, 'message' => 'ค้นหาข้อมูลสำเร็จ.']);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'message' => 'เกิดข้อผิดพลาด.']);
        }
    }

    public function cu(Request $req) {
        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator = Validator::make($req->all(), [
                'case_group' => 'required',
        ],[
            'case_group.required' => 'กรุณากรอก ชื่อ.',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        if($req->case_group_id) {
            $caseGroup = CaseGroup::find($req->case_group_id);
            $caseGroup->case_group    = $req->case_group;
            $caseGroup->is_active     = $req->is_active;
            $caseGroup->updated_by    = Auth::id();
            try {
                $caseGroup->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "case_group",
                    "errors" => $e
                ];
            }
        }else{
            $caseGroup = new CaseGroup();
            $caseGroup->case_group    = $req->case_group;
            $caseGroup->is_active     = $req->is_active;
            $caseGroup->created_by    = Auth::id();
            $caseGroup->updated_by    = Auth::id();
            try {
                $caseGroup->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "case_group",
                    "errors" => $e
                ];
            }
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
        
    }


    public function destroy(Request $req) {
        try {
            $item = CaseGroup::findOrFail($req->id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'CaseGroup not found.']);
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