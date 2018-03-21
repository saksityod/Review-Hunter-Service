<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\SocialMedia;
use Auth;
use DB;
use Validator;
use Exception;

class SocialMediaController extends Controller {

    public function __construct() {
        $this->middleware('jwt.auth');
    }

    public function get_all(Request $req) {
        $perpage = $req->perpage?$req->perpage:10;
        $data = DB::table('social_media')->orderBy('social_media_name','ASC')->paginate($perpage);
        return $data;
    }

    public function get_one($id) {
        try {
            $data = SocialMedia::findOrFail($id);
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
                'socialMediaName' => 'required',
        ],[
            'socialMediaName.required' => 'กรุณากรอก ชื่อ.',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        $social = new SocialMedia();
        $social->social_media_name  = $req->socialMediaName;
        $social->is_active          = $req->is_active;
        $social->created_by         = Auth::id();
        $social->updated_by         = Auth::id();
        try {
            $social->save();
        } catch (Exception $e) {
            $errors[] = [
                "table_name" => "social_media",
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
                'socialMediaName' => 'required',
        ]);

        if($validator->fails()){$errors_validator[] = $validator->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        $social = SocialMedia::find($id);
        $social->social_media_name  = $req->socialMediaName;
        $social->is_active          = $req->is_active;
        $social->updated_by         = Auth::id();
        try {
            $social->save();
        } catch (Exception $e) {
            $errors[] = [
                "table_name" => "social_media",
                "errors" => $e
            ];
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }

    public function destroy($id) {
        // try {
        //     $social = SocialMedia::find($id);
        //     // if(count($social->doctorTarget) == 0){
        //         if($social->delete()){
        //             return response()->json(['status' => 200, 'message' => 'ลบข้อมูลสำเร็จ.']);
        //         }
        //     // }
        //     return response()->json(['status' => 400, 'message' => 'ไม่สามารถลบข้อมูลนี้ได้.']);
        // }catch (ModelNotFoundException $e) {
        //     return response()->json(['status' => 404, 'message' => 'เกิดข้อผิดพลาด.']);
        // }

        try {
            $item = SocialMedia::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'SocialMedia not found.']);
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