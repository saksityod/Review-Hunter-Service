<?php

namespace App\Http\Controllers;

use App\Province;
use App\Amphur;
use App\District;
use App\Nationality;
use App\SocialMedia;
use App\CaseType;
use App\CaseGroup;
use App\Doctor;
use App\MedicalProcedure;
use App\DiscountType;
use App\Patient;
use App\PatientSocialMedia;
use App\UsageType;
use App\Pr;
use App\Folder;
use App\AppointmentType;
use App\ArticleType;
use App\CaseContact;
use App\Stage;
use App\SurgeryHistory;
use App\PatientCase;
use App\CaseFollowUp;
use App\CasePrice;
use App\CaseSocialMedia;
use App\CaseSupervised;
use App\CaseCoordinate;
use App\CaseAppointment;
use App\CaseContract; //waiting upload files
use App\CasePR;
use App\CaseArticle; //waiting upload files
use App\CaseContractDoc; // new
use App\CaseArticleDoc; // new
use App\CaseStage;
use App\CaseStageAlert;
use App\CaseStageDoc;
use App\WorkflowStage;
use App\CaseFile;
use App\CaseFolder;
use App\User;
use App\UserRole;
use App\MailAlertTime;

use ZipArchive;
use Mail;

use DateTime;
use object;
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

class BAController extends Controller {
    
    public function __construct() {
        $this->middleware('jwt.auth',['except' => ['download_case_stage_doc','mail_alert_time']]);
    }

    public function get_dataOnload(Request $req){
        
        $data['host']               = url('/');
        $data['province']           = Province::all();
        $data['nationality']        = Nationality::all();
        $data['socialMedia']        = SocialMedia::where('is_active',1)->get();
        $data['doctor']             = $this->get_doctor();
        $data['medicalProcedure']   = MedicalProcedure::where('is_active',1)->get();
        $data['discountType']       = DiscountType::all();
        $data['caseType']           = CaseType::where('is_active',1)->get();
        $data['casegroup']          = CaseGroup::where('is_active',1)->get();
        $data['currentDate']        = date('d-m-Y');
        $data['usageType']          = UsageType::with('usageItem')->get();
        $data['pr']                 = Pr::all();
        $data['getUserAlert']       = $this->get_user_alert();
        $data['articleType']        = ArticleType::all();
        $data['appointmentType']    = AppointmentType::all();
        $data['supervised']         = db::table('lportal.user_')
                                        ->select('userId','screenName','firstName','lastName')->get();
        return $data;
    }

    public function dateswap( $datadate ) {
        $datearray = explode("/",$datadate);
        if (strlen($datadate) > 3) {
        $meyear = $datearray[2] + 543;
        $datadate=$datearray[0]."/".$datearray[1]."/".$meyear;
        }
        return $datadate;
    }

    public function destoryFolder(Request $req){
        try {

            $case_id    = $req->case_id;
            $folder_id  = $req->folder_id;

            $case = PatientCase::select('case_id','patient_id')->where('case_id',$case_id)->first();        
            $folder = Folder::where('folder_id',$folder_id)->first();

            // child folder
            if($folder->parentFolder){
                $path = public_path().'/uploads/ba/'.$case->patient_id.'/'.$case->case_id.'/file/'.$folder->parentFolder->folder_name.'/'.$folder->folder_name;
                $folder->caseFolder()->delete();
                if($folder->caseFile) $folder->caseFile()->delete();
                File::deleteDirectory($path);
                if($folder->is_template !=1)    $folder->delete();
            }else{
                // parent folder
                if($folder->subFolder){
                    foreach ($folder->subFolder as $sub) {
                        if($sub->caseFolder)   $sub->caseFolder()->delete();
                        if($sub->caseFile)     $sub->caseFile()->delete();
                        File::deleteDirectory(public_path().'/uploads/ba/'.$case->patient_id.'/'.$case->case_id.'/file/'.$sub->parentFolder->folder_name.'/'.$sub->folder_name);
                        if($sub->is_template !=1) $sub->delete();
                    }
                }
                $folder->caseFolder()->delete();
                if($folder->caseFile) $folder->caseFile()->delete();
                File::deleteDirectory(public_path().'/uploads/ba/'.$case->patient_id.'/'.$case->case_id.'/file/'.$folder->folder_name);
                if($folder->is_template !=1)    $folder->delete();
            }
            return response()->json(['status' => 200,'message'=>'สำเร็จ!! ลบแฟ้มแล้ว.']);
        } catch (Exception $e) {
            return response()->json(['status' => 400,'message'=>'เกิดข้อผิดพลาด!! ไม่สามารถลบแฟ้มได้.','error'=>$e]);
        }
    }

    public function mail_alert_time(){
        try {
            $num_date = MailAlertTime::lists('alert_time');
            if($num_date){
                $today = date('Y-m-d');
                $send_date = [];
                foreach ($num_date as $date) {
                    array_push($send_date, date('Y-m-d', strtotime($today.'-'.$date.' days')));
                }
                // $caseFolders = caseFolder::whereNOTNULL('date_of_pass')
                //                 ->where('is_pass',1)
                //                 ->whereIn('date_of_pass',$send_date)
                //                 ->with(['patientCase','folder','patientCase.patient','patientCase.procedure'])
                //         ->orderby('case_id','asc')
                //         ->get(['case_id','folder_id','date_of_pass']);

                $cases = PatientCase::with(['caseFolder'=>function($qry) use ($send_date){
                                $qry->whereNOTNULL('date_of_pass');
                                $qry->where('is_pass',1);
                                $qry->whereIn('date_of_pass',$send_date);
                            },'caseFolder.folder','patient','procedure'])
                        ->whereHas('caseFolder',function($qry) use ($send_date){
                                $qry->whereNOTNULL('date_of_pass');
                                $qry->where('is_pass',1);
                                $qry->whereIn('date_of_pass',$send_date);
                            })
                        ->get();
                // return $cases;
                if($cases){
                    // $to = UserRole::where('roleId',22307)->with('user')->get()->lists('user.emailAddress');
                    $from = 'gjtestmail2017@gmail.com';
                    $to = ['work.zaimon@gmail.com'];

                    foreach($cases as $case) {
                        $temp_arr = [];
                        foreach($case->caseFolder as $cf) {
                            array_push($temp_arr,$cf->folder->folder_screen_name);
                        }
                        $data = [   
                            "patient_name"      => $case->patient->patient_name,
                            "procedure_name"    => $case->procedure->procedure_name,
                            "hn_no"             => $case->patient->hn_no,
                            "vn_no"             => $case->vn_no,
                            "folder_screen_name"=> $temp_arr
                        ];

                        Mail::send('emails.mail_alert_time', $data, function($message) use ($from, $to) {
                            $message->from($from, 'Review Hunter');
                            $message->to($to)->subject('แจ้งเตือน ระบบ Review Hunter รูปภาพสำหรับเขียนรีวิวพร้อมแล้ว');
                        });
                    }
                }else{
                    return response()->json(['status' => 200,'message'=>'Don\'t have data']);
                }
            }else{
                return response()->json(['status' => 200,'message'=>'Don\'t have alert time']);
            }
            return response()->json(['status' => 200,'message'=>'Send Email Success']);
        } catch (Exception $e) {
            return response()->json(['status' => 400,'message'=>'Error']);
        }
    }

    public function updateFolder(Request $req){
        try {
            
            if(isset($req->folder_name)){ 
                $folder = Folder::findOrFail($req->folder_id);
                if($folder->is_template == 0){
                    $folder->folder_name = $req->folder_name;
                    $folder->save();
                    return response()->json(['status' => 200,'data'=>$folder->folder_name]);    
                }else{
                    return response()->json(['status' => 400,'error'=>'ไม่สามารถแก้ไขแฟ้มข้อมูลหลักได้']); 
                }
            }else{
                $case_folder = CaseFolder::where('folder_id',$req->folder_id)->where('case_id',$req->case_id)->first();
                $data = [];
                if(isset($req->is_active))    $case_folder->is_active = $req->is_active;
                if(isset($req->is_open))      $case_folder->is_open   = $req->is_open;
                if(isset($req->is_pass)){
                    $case_folder->is_pass       = $req->is_pass;
                    if($req->is_pass == 0){
                        $case_folder->date_of_pass  = null;
                    }else{
                        $case_folder->date_of_pass  = date('Y-m-d');
                    }
                }
                $case_folder->save();
                return response()->json(['status' => 200,'data'=>$case_folder->folder->folder_name]);    
            }
        } catch (Exception $e) {
            return response()->json(['status' => 400,'error'=>'ระบบไม่สามารถ แก้ไขข้อมูลได้']);
        }
    }

    public function get_folderSummary(Request $req){
        try {
            $parent = Folder::find($req->folder_id);
            if($parent->parentFolder){
                $parent = Folder::find($parent->parentFolder->folder_id);
            }
            
            $rs =  DB::select(' SELECT  (   
                                            SELECT COUNT(CS.is_pass)
                                            FROM folder FL
                                            INNER JOIN case_folder CS ON CS.folder_id = FL.folder_id
                                            WHERE FL.folder_parent_id IS NOT NULL
                                            AND FL.folder_parent_id = ?
                                            AND CS.case_id = ?
                                            AND CS.is_pass = 1
                                            GROUP BY FL.folder_parent_id DESC
                                        ) as f_pass,
                                        (   
                                            SELECT COUNT(CS.is_pass)
                                            FROM folder FL
                                            INNER JOIN case_folder CS ON CS.folder_id = FL.folder_id
                                            WHERE FL.folder_parent_id IS NOT NULL
                                            AND FL.folder_parent_id = ?
                                            AND CS.case_id = ?
                                            GROUP BY FL.folder_parent_id DESC
                                        ) as f_all
                                FROM folder
                                GROUP BY f_pass',[  $parent->folder_id,$req->case_id,
                                                    $parent->folder_id,$req->case_id]);
            return response()->json(['status'=>200,'data'=>$rs]);
        } catch (Exception $e) {
            return response()->json(['status'=>400,'error'=>$e]);
        }
    }

    public function get_folder($case_id){
        return Folder::whereNull('folder_parent_id')->where('is_active',1)
                    ->with(['subFolder.caseFolder'=>function($query) use ($case_id){
                        $query->where('case_id',$case_id);
                    }])
                    ->wherehas('caseFolder',function($query) use ($case_id){
                        $query->where('case_id',$case_id);
                    })
                    ->get();
    }

    public function get_caseList(Request $req){
        // return $req->all();
        $search     = $req->search;
        $caseType   = $req->caseType;
        $procedure  = $req->procedure;
        $social     = $req->social;
        $hn         = $req->hn;
        $review     = $req->review;
        $expDate    = $req->expDate;
        $vn         = $req->vn;
        $followup   = $req->followup;
        $case_group = $req->case_group;

        $perpage = $req->perpage?$req->perpage:10;
        $myQry = PatientCase::query();
        $myQry = $myQry->with(['patient','patientSocialMedia.socialMedia','procedure','caseType','caseGroup','caseFollowUp','caseContract'  ]);
        if($caseType){      $myQry = $myQry->where('case_type_id',$caseType);   }
        if($procedure){     $myQry = $myQry->where('procedure_id',$procedure);   }
        if($review){   
            if($review==1)  $myQry = $myQry->where('is_bad_case',1);   
            if($review==2)  $myQry = $myQry->where('is_good_review',1);   
            if($review==3)  $myQry = $myQry->where('is_good_case',1);   
            if($review==4)  $myQry = $myQry->where('is_good_case',0)->where('is_good_review',0)->where('is_bad_case',0);  
        }
        if($social){  
            $data = $myQry->wherehas('patientSocialMedia',function($qry) use($social){
                        $qry->where('social_media_id',$social);     });     }
        if($search){  
            $data = $myQry->wherehas('patient',function($qry) use($search){
                        $qry->where('patient_name', 'like','%'.$search.'%');    });     }
        if($hn){  
            $data = $myQry->wherehas('patient',function($qry) use($hn){
                        $qry->where('hn_no', 'like','%'.$hn.'%');       });     }
        if($vn){  
            $data = $myQry->where('vn_no', 'like','%'.$vn.'%'); }

        if($case_group){  
            $data = $myQry->where('case_group_id', $case_group); }
 
        if($expDate == '1') {
            $data = $myQry->wherehas('caseContract',function($qry) {
                        $qry->whereDate('contract_end_date', '>=' ,date("Y-m-d"));    
                    });     
        }
        if($expDate == '0'){ 
            $data = $myQry->doesntHave('caseContract')->orWhereHas(
            'caseContract',function($qry) {  
                $qry->whereDate('contract_end_date','<' ,date("Y-m-d"));     
            });
        }
            
        if($followup){  
            $data = $myQry->wherehas('caseFollowUp',function($qry) use($followup){
                        $qry->where('procedure_id',$followup);       });     }
        // $data = $myQry->paginate($perpage);
        $data = $myQry->orderby('case_id','DESC')->paginate($perpage);
        return response()->json($data);
    }

    public function get_onePatient(Request $req){
        $data = Patient::where('patient_id',$req->patient_id)
                ->with(['social','surgery'])
                ->first();
        return $data;
    }

    public function cu_followup(Request $req){
        try {
            $case_id = $req->case_id;
            $followups = $req->case_followup;
            foreach ($followups as $followup) {
                if($followup['followup_id'] !=''){
                    $fu = CaseFollowUp::findOrFail($followup['followup_id']);
                    $fu->procedure_id    = $followup['procedure_id'];
                    $fu->followup_year  = $followup['followup_year'];
                    $fu->remark = $followup['remark'];
                    $fu->updated_by     = Auth::id();
                    $fu->save();
                }else{
                    $fu = new CaseFollowUp();
                    $fu->case_id        = $followup['case_id'];
                    $fu->procedure_id    = $followup['procedure_id'];
                    $fu->followup_year  = $followup['followup_year'];
                    $fu->remark = $followup['remark'];
                    $fu->updated_by     = Auth::id();
                    $fu->save();
                }
            }
            $data = CaseFollowUp::where('case_id',$case_id)->get();
            return response()->json(['status'=>200,'data'=>$data]);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }

    public function get_oneCase(Request $req){
        $case_id = $req->case_id;
        $data = PatientCase::where('case_id',$case_id)
                ->with(['patient.social','patientSurgery','caseSupervised.user','caseType',
                        'caseGroup' ,'doctor','procedure','casePrice','caseSocialMedia',
                        'caseCoordinate','caseFollowUp','caseAppointment.supervisedBy',
                        'caseContract.caseContractDoc','casePr','caseArticle.caseArticleDoc',
                        'caseArticle.writer','caseStage'])
                ->first();
        $data['folder'] = $this->get_case_folder($case_id);
        $data['caseStageHistory'] = $this->case_stage_history($case_id);
        return $data;
    }

    public function get_case_folder($case_id){
        $folder = db::table('case_folder as cf')
                        ->where('cf.case_id',$case_id)
                    ->join('folder as f' , 'cf.folder_id','=','f.folder_id')
                        ->whereNull('f.folder_parent_id')
                    ->get();
        foreach ($folder as $fi => $f) {
            $folder[$fi]->caseFile = db::table('case_file as fcf')
                                            ->where('fcf.case_id',$case_id)
                                            ->where('fcf.folder_id',$f->folder_id)
                                            ->get();

            $folder[$fi]->subFolder = db::table('case_folder as csf')
                                            ->where('csf.case_id',$case_id)
                                            ->where('sf.folder_parent_id',$f->folder_id)
                                        ->join('folder as sf' , 'csf.folder_id','=','sf.folder_id')
                                            ->whereNOTNULL('sf.folder_parent_id')
                                        ->get();

            foreach ($folder[$fi]->subFolder as $sfi => $sf) {
                $folder[$fi]->subFolder[$sfi]->caseFile = db::table('case_file as fcsf')
                                                                ->where('fcsf.case_id',$case_id)
                                                                ->where('fcsf.folder_id',$sf->folder_id)
                                                                ->get();
            }
        }

        foreach ($folder as $fi => $f) {
            if(sizeof($f->subFolder) > 0){
                foreach ($f->subFolder as $sfi => $sf) {
                    if(sizeof($sf->caseFile) == 0){
                        unset($f->subFolder[$sfi]);
                    }
                }
            }
            if(sizeof($f->subFolder) == 0 && sizeof($f->caseFile) == 0){
                 unset($folder[$fi]);
            }
        }
        return $folder; 
    }

    public function get_case_file(Request $req){
        $case_id = $req->case_id;
        $file = CaseFile::where('case_id',$case_id)->get();
        return $file; 
    }

    public function make_directory(Request $req) {
        $parent = '';
        $case = PatientCase::select('case_id','patient_id')->where('case_id',$req->case_id)->first();        
        $parent = $req->folder_parent_id?Folder::where('folder_id',$req->folder_parent_id)->first()->folder_name.'/':'';
        $folder_name = "f_".date('Ymd_his');

        $folder = new Folder;
        $folder->folder_screen_name = $req->folder_screen_name;
        $folder->folder_name        = $folder_name;
        $folder->is_active          = 1;
        $folder->is_template        = 0;
        $folder->folder_parent_id   = $req->folder_parent_id?$req->folder_parent_id:null;
        // $folder->user_id            = Auth::user()->userId;
        $folder->created_by         = Auth::id();
        if($folder->save()){
            $path_folder = public_path().'/uploads/ba/'.$case->patient_id.'/'.$case->case_id.'/file/'.$parent.$folder_name;
            @mkdir($path_folder, 0777, true);

            $caseFolder = new CaseFolder();
            $caseFolder->case_id    = $req->case_id;
            $caseFolder->folder_id  = $folder->folder_id;
            $caseFolder->is_open    = 1;
            $caseFolder->is_pass    = 0;
            $caseFolder->created_by = Auth::id();
            if($caseFolder->save()){
                return response()->json(['status' => 200,'data'=>$this->get_case_folder($req->case_id)]);
            }
        }
    }

    public function delete_file(Request $req) {
        try {
            if($req->method == 'case_contract'){
                $model = CaseContractDoc::where('case_contract_doc_id',$req->file_id)->first();
                if($model->delete()){  
                    File::delete(public_path().$model->contract_path);
                    return response()->json(['status'=>200]);  
                }else{
                    return response()->json(['status'=>400]);    
                }
            }
            if($req->method == 'case_article'){
                $model = CaseArticleDoc::where('case_article_doc_id',$req->file_id)->first();  
                if($model->delete()){
                    File::delete(public_path().$model->article_path);  
                    return response()->json(['status'=>200]);  
                }else{
                    return response()->json(['status'=>400]);    
                }
            }
            if($req->method == 'case_file'){
                $model = CaseFile::where('file_id',$req->file_id)->first();
                // return response()->json(public_path().$model->image_path);  
                if( $model->delete()){
                    File::delete(public_path().$model->image_path);
                    return response()->json(['status'=>200]);  
                }else{
                    return response()->json(['status'=>400]);    
                }
            }
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }

    public function del_rec(Request $req) {
        try {
            // return $req->all();
            $method = $req->method;
            if($method == 'patient_social_media')   PatientSocialMedia::find($req->id)->delete();
            if($method == 'surgery_history')        SurgeryHistory::find($req->id)->delete();
            if($method == 'case_followup')          CaseFollowUp::find($req->id)->delete();
            if($method == 'case_price')             CasePrice::find($req->id)->delete();
            if($method == 'case_social_media')      CaseSocialMedia::find($req->id)->delete();
            if($method == 'case_coordinate')        CaseCoordinate::find($req->id)->delete();
            if($method == 'case_appointment')       CaseAppointment::find($req->id)->delete();
            if($method == 'case_pr')                CasePR::find($req->id)->delete();

            if($method == 'case_contract'){
                $model = CaseContract::find($req->id);
                if($model->caseContractDoc){
                    File::deleteDirectory(public_path().$model->contract_path);
                    $model->CaseContractDoc()->delete();
                    $model->delete();
                    return response()->json(['status'=>200]);
                }
            }
            if($method == 'case_article'){
                $model = CaseArticle::find($req->id);
                if($model->caseArticleDoc){
                    File::deleteDirectory(public_path().$model->article_path);
                    $model->CaseArticleDoc()->delete();
                    $model->delete();  
                    return response()->json(['status'=>200]);
                }              
            }
            return response()->json(['status' => 200]);
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }

    public function get_user(Request $req){
        try {
            $supervised = explode('|',$req->user);
            array_shift($supervised);
            $myQry = User::query();
            $myQry = $myQry->whereNotIn('userId',$supervised)->where('firstName', 'like','%'.$req->search.'%');
            if($req->method == 'admin') {
                $myQry = $myQry->where(function($qry) {
                    $qry->where('userId',24093);    /* rhadmin  */
                    $qry->orWhere('userId',24084);  /* rhco     */
                    $qry->orWhere('userId',24057);  /* rhmgr    */
                });
            }
            return $myQry->get(['userId','screenName','firstName','lastName']);
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }

    public function get_supervised_user(Request $req){
        $search = $req->search;
        $case_id = $req->case_id;

        return user::where('firstName', 'like','%'.$search.'%')
                // ->with(['caseSupervised'])
                ->whereHas('caseSupervised',function($qry) use ($case_id){
                    $qry->where('case_id',$case_id);
                })
                ->get(['userId','firstName','lastName','screenName']);
    }

    public function get_amphur(Request $req){
        return Amphur::where('province_id',$req->province_id)
        ->wherehas('district.zipcode', function ($query) {
            $query->whereNotNull('zipcode');
        })
        ->get();
    }

    public function get_district(Request $req){
        return District::where('amphur_id',$req->amphur_id)->with('zipcode')
        ->wherehas('zipcode', function ($query) {
            $query->whereNotNull('zipcode');
        })
        ->get();
    }

    public function get_doctor(){
        return Doctor::where('is_active',1)->get();
    }
    
    public function import_file(Request $req) {
        try {
            // return $req->all();
            $folder_id  = $req->folder_id;
            $files      = $req->file('file');
            $case_id    = $req->case_id;

            $case       = PatientCase::select('case_id','patient_id')->where('case_id',$req->case_id)->first();        
            $folder     = Folder::where('folder_id',$folder_id)->with('parentFolder')->first();
            $path       = '/uploads/ba/'.$case->patient_id.'/'.$case->case_id.'/file';
            if($folder->parentFolder)   $path .= '/'.$folder->parentFolder->folder_name;
            $path       .= '/'.$folder->folder_name;
            $fullPath   = public_path().$path;

            if(!empty($files)){
                foreach ($files as $file) {
                    $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                    if($file->move($fullPath,$filename)){
                        $item = new CaseFile();
                        $item->case_id      = $case_id;
                        $item->folder_id    = $folder_id;
                        $item->file_name    = $filename;
                        $item->image_path   = $path.'/'.$filename;
                        $item->user_id      = Auth::user()->userId;
                        $item->created_by   = Auth::id();
                        try {
                            $item->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "item",
                                "errors" => $e
                            ];
                        }
                        $result[] = $item;
                    }else{
                        return response()->json(['status' => 400, 'errors' =>  'upload file is fail.']);
                    }              
                } 
            }
            return response()->json(['status'=>200,'data'=>$this->get_case_folder($case_id)]);
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }

    public function get_new_case_stage(Request $req) {
        try {
            return Stage::findOrFail(1);
            
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }  

    public function get_stage(Request $req) {
        try {
            if($req->case_stage_id == '')   $case_stage_id = 1;
            else                            $case_stage_id = $req->case_stage_id;
            $caseStage = CaseStage::where('case_stage_id',$case_stage_id)->with(['fromStage'=>function($qry){
                $qry->select(['stage_id','stage_name']);
            },'toStage'=>function($qry){
                $qry->select(['stage_id','stage_name']);
            }])->first(['case_stage_id','from_stage_id','to_stage_id']);
            return $caseStage;
            
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }   

    public function action_to(Request $request){
        $workflow_stage = DB::select("
            select to_stage_id, status 
            from workflow_stage
            where from_stage_id = ?
        ",array($request->stage_id));
        
        if (empty($workflow_stage)) {
            return response()->json([]);
        }
            //select stage_id, stage_name, role_id , '{$workflow_stage[0]->status}' status
        $actions = DB::select(" 
            select stage_id, stage_name, role_id ,stage_name as status
            from stage
            where stage_id in ({$workflow_stage[0]->to_stage_id})
            order by stage_id
        "); 
        
        return response()->json($actions);      
    }

    public function send_to_stage(Request $request) {
        $actions = DB::select(" 
            SELECT u.userId, u.screenName, u.emailAddress
            from stage s
            left join lportal.role_ r
            on r.roleId = s.role_id
            inner join lportal.users_roles us
            on us.roleId = r.roleId
            inner join lportal.user_ u
            on u.userId = us.userId
            where s.stage_id = {$request->stage_id}
            order by s.stage_id
        "); 
        
        return response()->json($actions);      
    }

    public function get_user_alert() {
        $items = DB::select("
            SELECT s.userId, s.emailAddress, s.screenName
            FROM lportal.user_ s, lportal.users_roles ur, lportal.role_ r 
            where s.userId = ur.userId 
            and ur.roleId = r.roleId 
            and r.roleId in (22301,22302,22303,22304,22305,22306,22307,22308,22309,22310,22311,22312,22313)
            group by s.screenName
            order by s.screenName ASC
        ");
        return $items;
    }

    public function download_case_stage_doc($case_stage_id) {
        // return $req->all();

        $files = DB::table('Case_stage_doc')->select('doc_path')->where('case_stage_id',$case_stage_id)->get();
        if(empty($files)) {
            return response()->json(['status'=>400, 'errors' => 'file does not exist']);
        }

        // $base_path = base_path() . implode("','",array_pop(explode('/',$files[0]->doc_path)));
        $zip_path = public_path().'/downloads';
        $zipFileName = 'stage_'.date('Ymd_his').'_'.$case_stage_id.".zip";
        $zip = new ZipArchive;

        if ($zip->open($zip_path."/". $zipFileName, ZipArchive::CREATE) === TRUE) {    
            foreach ($files as $file) {
                $temp = explode('/',$file->doc_path);
                $filename = end($temp);
                $set_utf8_filename = iconv('UTF-8','windows-874',$filename);
                $zip->addFile(public_path().$file->doc_path, $set_utf8_filename);
            }
            //return response()->json($zip);
            $zip->close();
        }

        $filetopath = $zip_path."/".$zipFileName;
        if(file_exists($filetopath)) {
            return response()->json(['status'=>200,'file_path'=>'/downloads/'.$zipFileName]);
        }else{
            return response()->json(['status'=>200]);
        }
    }
    
    public function case_stage_history($case_id) {
        $items = DB::select("
            SELECT a.case_stage_id,a.remark,a.created_dttm, fs.stage_name from_stage_name, fu.screenName from_user_name, ts.stage_name to_stage_name, tu.screenName to_user_name 
            FROM case_stage a
            left outer join stage fs
            on a.from_stage_id = fs.stage_id
            left outer join stage ts
            on a.to_stage_id = ts.stage_id
            left outer join lportal.user_ fu
            on a.from_user_id = fu.userId
            left outer join lportal.user_ tu
            on a.to_user_id = tu.userId     
            where case_id = ?
            order by created_dttm asc
        ", array($case_id));
        
        foreach ($items as $i) {
            $alerts = DB::select("
                select a.user_id, u.screenName, u.emailAddress
                from case_stage_alert a
                inner join lportal.user_ u
                on a.user_id = u.userId
                where a.case_stage_id = ?
            ", array($i->case_stage_id));
            
            $docs = DB::select("
                select a.doc_path
                from case_stage_doc a
                where a.case_stage_id = ?
            ", array($i->case_stage_id));
            
            $i->alerts = $alerts;
            $i->docs = $docs;
            
        }
        
        return $items;
    }
   
    /*public function cu_contract(Request $filesData) {
        $request = json_decode($filesData->formdata, true);
        //return response()->json($filesData->all());
        $errors = [];
        $errors_validator = [];
        $return_data = [];

        DB::beginTransaction();

        if(!empty($request['case_contract'])) {
            foreach($request['case_contract'] as $row) {
                $validator_case_contract = Validator::make($row, [
                    'procedure_id'  => 'required|integer',
                    'contract_start_date' => 'required',
                    'contract_end_date' => 'required',
                    'is_post'       => 'required',
                    'is_usage'      => 'required',
                    'is_picture_vdo' => 'required',
                    'is_review'     => 'required',
                    'is_pr'         => 'required',
                    'is_group_post' => 'required',
                    'is_send_picture' => 'required',
                    'is_other'      => 'required'
                ]);
            }
            if($validator_case_contract->fails()){$errors_validator[] = $validator_case_contract->errors();}
        }

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        if(!empty($request['case_contract'])) {
            foreach($request['case_contract'] as $s) {
                if(empty($s['contract_id'])) {
                    //add
                    $case_contract = new CaseContract;
                    $case_contract->seq_id = $s['seq_id'];
                    $case_contract->case_id = $current_patient_case_id;
                    $case_contract->procedure_id = $s['procedure_id'];
                    $case_contract->contract_start_date = $s['contract_start_date'];
                    $case_contract->contract_end_date = $s['contract_end_date'];
                    $case_contract->is_post = $s['is_post'];
                    $case_contract->is_usage = $s['is_usage'];
                    $case_contract->is_picture_vdo = $s['is_picture_vdo'];
                    $case_contract->is_review = $s['is_review'];
                    $case_contract->is_pr = $s['is_pr'];
                    $case_contract->is_group_post = $s['is_group_post'];
                    $case_contract->is_send_picture = $s['is_send_picture'];
                    $case_contract->is_other = $s['is_other'];
                    $case_contract->created_by = Auth::id();
                    $case_contract->updated_by = Auth::id();
                    try {
                        $case_contract->save();
                        $current_contract_id = $case_contract->contract_id;
                        array_push($return_data, ['case_contract' => $case_contract]);
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "case_contract",
                            "errors" => $e
                        ];
                    }

                    $result = array();
                    $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/case_contract/' . $current_contract_id . '/';
                    CaseContract::where('contract_id',$current_contract_id)->update(['contract_path',$path]);
                    foreach ($filesData->file() as $key => $f) {
                        $files_check = explode('|', $key);
                        if($s['file_name_check'] == $files_check[0]) {
                            $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                            $f->move($path,$filename);
                            $item = CaseContractDoc::firstOrNew(array('contract_id' => $current_contract_id, 'doc_path' => 'case_contract/' . $current_contract_id . '/' . $f->getClientOriginalName()));
                            $item->contract_id = $current_contract_id;
                            $item->created_by = Auth::id();
                            try {
                                $item->save();
                                $result[] = $item;
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_contract_doc",
                                    "errors" => $e
                                ];
                            }
                        }
                    }
                } else {
                    //update
                    $case_contract = CaseContract::find($s['contract_id']);
                    $case_contract->seq_id = $s['seq_id'];
                    $case_contract->case_id = $current_patient_case_id;
                    $case_contract->procedure_id = $s['procedure_id'];
                    $case_contract->contract_start_date = $s['contract_start_date'];
                    $case_contract->contract_end_date = $s['contract_end_date'];
                    $case_contract->is_post = $s['is_post'];
                    $case_contract->is_usage = $s['is_usage'];
                    $case_contract->is_picture_vdo = $s['is_picture_vdo'];
                    $case_contract->is_review = $s['is_review'];
                    $case_contract->is_pr = $s['is_pr'];
                    $case_contract->is_group_post = $s['is_group_post'];
                    $case_contract->is_send_picture = $s['is_send_picture'];
                    $case_contract->is_other = $s['is_other'];
                    $case_contract->contract_path = "/master_piece/public/case_contract/";
                    $case_contract->updated_by = Auth::id();
                    try {
                        $case_contract->save();
                        array_push($return_data, ['case_contract' => $case_contract]);
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "case_contract",
                            "errors" => $e
                        ];
                    }

                    $result = array();
                    $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/case_contract/' . $s['contract_id'] . '/';
                    foreach ($filesData->file() as $key => $f) {
                        $files_check = explode('|', $key);
                        if($s['file_name_check'] == $files_check[0]) {
                            CaseContractDoc::where('contract_id', '=', $s['contract_id'])->delete();
                            $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                            $f->move($path,$filename);
                            $item = CaseContractDoc::firstOrNew(array('contract_id' => $s['contract_id'], 'doc_path' => 'case_contract/' . $s['contract_id'] . '/' . $f->getClientOriginalName()));

                            $item->contract_id = $s['contract_id'];
                            $item->created_by = Auth::id();
                            try {
                                $item->save();
                                $result[] = $item;
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_contract_doc",
                                    "errors" => $e
                                ];
                            }
                        }
                    }
                }
            }

            empty($errors) ? DB::commit() : DB::rollback();
            empty($errors) ? $status = 200 : $status = 400;
            return response()->json(['status' => $status, 'errors' => $errors, 'rs' => $return_data]);
        }
    }*/

    public function cu(Request $filesData) {
        // return $filesData->all();
        $request = json_decode($filesData->formdata, true);
        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        if(empty($request)) {
            return response()->json(['status' => 400, 'data' => 'formdata not found.']);
        } else {
            
            $current_patient_id = '';  
            $current_patient_case_id = '';  
            $current_case_stage = '';
            $current_contract_id = '';
            $current_case_article = '';

            $validator_patient = Validator::make($request['patient'], [
                'patient_name'  => 'required|max:256',
                'birthday'       => 'required|max:13',
                'nationality_id'=> 'required',
                'home_no'        => 'required',
                'gender'        => 'required',
                'id_card'       => 'required|max:13',
                'mobile_no'     => 'required',
                'province_id'   => 'required',
                'amphur_id'     => 'required',
                'district_id'   => 'required',
            ],[
                'patient_name.required' => 'ข้อมูลส่วนตัว : กรุณากรอก ชื่อคนไข้.',
                'patient_name.max'      => 'ข้อมูลส่วนตัว : ชื่อคนไข้ ยาวเกินกำหนด.',
                'birthday.required'     => 'ข้อมูลส่วนตัว : กรุณากรอก วันเกิด.',
                'nationality_id.required'=> 'ข้อมูลส่วนตัว : กรุณาเลือก สัญชาติ.',
                'home_no.required'      => 'ข้อมูลส่วนตัว : กรุณากรอก เลขที่อยู่.',
                'gender.required'       => 'ข้อมูลส่วนตัว : กรุณาเลือก เพศ.',
                'id_card.required'      => 'ข้อมูลส่วนตัว : กรุณากรอก รหัสประจำตัวประชาชน.',
                'id_card.max'           => 'ข้อมูลส่วนตัว : รหัสประจำตัวประชาชน ยาวเกินกำหนด 13 หลัก.',
                'mobile_no.required'    => 'ข้อมูลส่วนตัว : กรุณากรอก เบอร์โทรศัพท์มือถือ.',
                'province_id.required'  => 'ข้อมูลส่วนตัว : กรุณาเลือก จังหวัด.',
                'amphur_id.required'    => 'ข้อมูลส่วนตัว : กรุณาเลือก อำเภอ.',
                'district_id.required'  => 'ข้อมูลส่วนตัว : กรุณาเลือก ตำบล.',
            ]);

            if($validator_patient->fails()){$errors_validator[] = $validator_patient->errors();}

            if(!empty($request['social_media'])) {
                foreach($request['social_media'] as $row) {
                    $validator_social_media = Validator::make($row, [
                        'social_media_id'           => 'required',
                        'user_link'                 => 'required|max:256',
                        // 'n_of_follwer'              => 'required|integer',
                    ],[ 'social_media_id.required'  => 'ข้อมูล Social Network : กรุณาเลือก ประเภทสื่อ.',
                        'user_link.required'        => 'ข้อมูล Social Network : กรุณากรอก บัญชีผู้ใช้งาน/ลิ้งค์.',
                        // 'n_of_follwer.required'     => 'ข้อมูล Social Network : กรุณากรอก  จำนวน Follower.',
                    ]);
                }
                if($validator_social_media->fails()){$errors_validator[] = $validator_social_media->errors();}
            }

            if(!empty($request['surgery_history'])) {
                foreach($request['surgery_history'] as $row) {
                    $validator_surgery_history = Validator::make($row, [
                        'surgery_year' => 'required|integer',
                        'history_name' => 'required|max:256',
                    ],[ 'surgery_year.required' => 'ประวัติศัลยกรรม : กรุณาเลือก ปี.',
                        'history_name.required' => 'ประวัติศัลยกรรม : กรุณากรอก หัตถการ.',
                    ]);
                }
                if($validator_surgery_history->fails()){$errors_validator[] = $validator_surgery_history->errors();}
            }

            //if(!empty($request['patient_case'])) {
                $validator_patient_case = Validator::make($request['patient_case'], [
                    'procedure_id'  => 'required',
                    'case_type_id'  => 'required',
                    'case_group_id' => 'required',
                    'doctor_id'     => 'required',
                    // 'suggested_by' => 'required|max:256',
                    // 'is_good_case' => 'required|boolean',
                    // 'is_bad_case' => 'required|boolean',
                    // 'is_good_review' => 'required|boolean',
                    // 'remark' => 'required|max:1000',
                    // 'stage_id' => 'required|integer',
                    // 'status' => 'required',
                ],['procedure_id.required'  => 'ข้อมูลการทำหัตถการ : กรุณาเลือก หัตถการ.',
                   'case_type_id.required'  => 'ข้อมูลการทำหัตถการ : กรุณาเลือก ประเภท Case.',
                   'case_group_id.required' => 'ข้อมูลการทำหัตถการ : กรุณาเลือก กลุ่มของ Case.',
                   'doctor_id.required'     => 'ข้อมูลการทำหัตถการ : กรุณาเลือก แพทย์.', 
               ]);
                if($validator_patient_case->fails()){$errors_validator[] = $validator_patient_case->errors();}
            //}

            if(!empty($request['case_followup'])) {
                foreach($request['case_followup'] as $row) {
                    $validator_case_followup = Validator::make($row, [
                        'procedure_id' => 'required|integer'
                    ],[ 'procedure_id.required' => 'หัตถการที่ควรทำ : กรุณาเลือก ชื่อหัตถการ.'
                    ]);
                }
                if($validator_case_followup->fails()){$errors_validator[] = $validator_case_followup->errors();}
            }

            if(!empty($request['case_price'])) {
                foreach($request['case_price'] as $row) {
                    $validator_case_price = Validator::make($row, [
                        'discount_type_id'  => 'required',
                        'offer_price'       => 'required',
                        'accept_price'      => 'required',
                        // 'remark' => 'required|max:1000',
                    ],[ 'discount_type_id.required' => 'ราคา : กรุณาเลือก ประเภทส่วนลด.',
                        'offer_price.required'      => 'ราคา : กรุณากรอก ราคาที่เสนอ.',
                        'accept_price.required'     => 'ราคา : กรุณากรอก ราคาที่ยอมจ่าย.',
                    ]);
                }
                if($validator_case_price->fails()){$errors_validator[] = $validator_case_price->errors();}
            }

            if(!empty($request['case_social_media'])) {
                foreach($request['case_social_media'] as $row) {
                    $validator_case_social_media = Validator::make($row, [
                        'social_media_id'   => 'required',
                        'link'              => 'required',
                        // 'pwd' => 'required|max:50',
                        // 'note' => 'required|max:1000',
                    ],[ 'social_media_id.required'  => 'ช่องทางลงสื่อ : กรุณาเลือก ประเภทส่วนลด.',
                        'link.required'             => 'ช่องทางลงสื่อ : กรุณาเลือก ลิงค์.',
                    ]);
                }
                if($validator_case_social_media->fails()){$errors_validator[] = $validator_case_social_media->errors();}
            }

            if(!empty($request['case_coordinate'])) {
                foreach($request['case_coordinate'] as $row) {
                    $validator_case_coordinate = Validator::make($row, [
                        // 'usage_id' => 'required|integer',
                    ]);
                }
                if($validator_case_coordinate->fails()){$errors_validator[] = $validator_case_coordinate->errors();}
            }

            if(!empty($request['case_appointment'])) {
                foreach($request['case_appointment'] as $row) {
                    $validator_case_appointment = Validator::make($row, [
                        'appointment_type_id' => 'required',
                        'appointment_date' => 'required',
                        'doctor_id' => 'required|integer',
                        // 'is_vdo_product' => 'required|boolean',
                        // 'is_vdo_rh' => 'required|boolean',
                        // 'is_picture_product' => 'required|boolean',
                        // 'is_picture_rh' => 'required|boolean',
                        // 'is_meet_doctor' => 'required|boolean',
                        // 'remark' => 'required|max:1000',
                    ],[ 'appointment_type_id.required'  => 'นัดหมาย : กรุณาเลือก ประเภทนัดหมาย.',
                        'appointment_date.required'     => 'นัดหมาย : กรุณาเลือก ณ วันที่.',
                        'doctor_id.required'            => 'นัดหมาย : กรุณาเลือก แพทย์.'
                    ]);
                }
                if($validator_case_appointment->fails()){$errors_validator[] = $validator_case_appointment->errors();}
            }

            if(!empty($request['case_contract'])) {
                foreach($request['case_contract'] as $row) {
                    $validator_case_contract = Validator::make($row, [
                        'procedure_id' => 'required|integer',
                        'contract_start_date' => 'required',
                        'contract_end_date' => 'required',
                        // 'is_post' => 'required|boolean',
                        // 'is_usage' => 'required|boolean',
                        // 'is_picture_vdo' => 'required|boolean',
                        // 'is_review' => 'required|boolean',
                        // 'is_pr' => 'required|boolean',
                        // 'is_group_post' => 'required|boolean',
                        // 'is_send_picture' => 'required|boolean',
                        // 'is_other' => 'required|boolean',
                    ],[ 'procedure_id.required'         => 'รายละเอียดสัญญา : กรุณาเลือก หัตถการ.',
                        'contract_start_date.required'  => 'รายละเอียดสัญญา : กรุณาเลือก วันเริ่มสัญญา.',
                        'contract_end_date.required'    => 'รายละเอียดสัญญา : กรุณาเลือก วันหมดสัญญา.'
                    ]);
                }
                if($validator_case_contract->fails()){$errors_validator[] = $validator_case_contract->errors();}
            }

            if(!empty($request['case_pr'])) {
                foreach($request['case_pr'] as $row) {
                    $validator_case_pr = Validator::make($row, [
                        'pr_id' => 'required',
                        'pr_plan_date' => 'required',
                        // 'is_picture' => 'required|boolean',
                        // 'is_vdo' => 'required|boolean',
                        // 'is_instragram' => 'required|boolean',
                        // 'is_facebook' => 'required|boolean',
                        'pr_actual_date' => 'required',
                    ],[ 'pr_id.required'            => 'รายละเอียดการประชาสัมพันธ์ : กรุณาเลือก รายการประชาสัมพันธ์.',
                        'pr_plan_date.required'     => 'รายละเอียดการประชาสัมพันธ์ : กรุณาเลือก ณ วันที่.',
                        'pr_actual_date.required'   => 'รายละเอียดการประชาสัมพันธ์ : กรุณาเลือก ในวันที่.'
                    ]);
                }
                if($validator_case_pr->fails()){$errors_validator[] = $validator_case_pr->errors();}
            }

            if(!empty($request['case_article'])) {
                foreach($request['case_article'] as $row) {
                    $validator_case_article = Validator::make($row, [
                        'article_name'          => 'required|max:256',
                        'article_type_id'       => 'required',
                        'writer'                => 'required',
                        'writing_start_date'    => 'required',
                        // 'writing_end_date' => 'required',
                        'plan_date'             => 'required'
                    ],[ 'article_name.required'         => 'บทความ   : กรุณากรอก ชื่อบทความ.',
                        'article_type_id.required'      => 'บทความ   : กรุณาเลือก ประเภทบทความ.',
                        'writer.required'               => 'บทความ   : กรุณากรอก ผู้เขียน.',
                        'writing_start_date.required'   => 'บทความ   : กรุณากรอก วันที่เริ่มเขียน.',
                        'plan_date.required'            => 'บทความ   : กรุณากรอก กำหนดเสร็จ.'
                    ]);
                }
                if($validator_case_article->fails()){$errors_validator[] = $validator_case_article->errors();}
            }

            if(!empty($request['case_stage'])) {
                $validator_case_stage = Validator::make($request['case_stage'], [
                    'from_stage_id' => 'required',
                    'to_user_id'        => 'required',
                    'to_stage_id'   => 'required',
                ],[ 'from_stage_id.required'=> 'Workflow   : กรุณาเลือก จากขั้นตอน.',
                    'to_user_id.required'   => 'Workflow   : กรุณาเลือก ส่งถึง.',
                    'to_stage_id.required'  => 'Workflow   : กรุณาเลือก ถึงขั้นตอน.'
                ]);
                if($validator_case_stage->fails()){$errors_validator[] = $validator_case_stage->errors();}
            }

            if(!empty($errors_validator)) {
                return response()->json(['status' => 400, 'errors' => $errors_validator]);
            }
            // return $filesData->all();
            if(empty($request['patient']['patient_id'])) {
                //add

                $patient = new Patient;
                $patient->hn_no             = $request['patient']['hn_no'];
                $patient->patient_name      = $request['patient']['patient_name'];
                $patient->nick_name         = $request['patient']['nick_name'];
                $patient->birthday          = $request['patient']['birthday'];
                $patient->nationality_id    = $request['patient']['nationality_id'];
                $patient->gender            = $request['patient']['gender'];
                $patient->id_card           = $request['patient']['id_card'];
                $patient->home_no           = $request['patient']['home_no'];
                $patient->moo               = $request['patient']['moo'];
                $patient->soi               = $request['patient']['soi'];
                $patient->road              = $request['patient']['road'];
                $patient->province_id       = $request['patient']['province_id'];
                $patient->amphur_id         = $request['patient']['amphur_id'];
                $patient->district_id       = $request['patient']['district_id'];
                $patient->mobile_no         = $request['patient']['mobile_no'];
                $patient->telephone_no      = $request['patient']['telephone_no'];
                $patient->career            = $request['patient']['career'];
                $patient->created_by        = Auth::id();
                $patient->updated_by        = Auth::id();
                try {
                    $patient->save();
                    $current_patient_id = $patient->patient_id;
                    $file = $filesData->file('btnUpload_profileImage')[0];
                    $patient = Patient::find($patient->patient_id);
                    $path = '/uploads/ba/'.$patient->patient_id.'/profile_image';

                    if(!empty($file)) {
                        $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'.$path;
                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                        $file->move($fullPath,$filename); 
                        $patient->image_path = $path.'/'.$filename;
                    }else{
                        $patient->image_path = '/uploads/ba/default.jpg';
                    }
                    $patient->save();
                } catch (Exception $e) {
                    $errors[] = [
                        "table_name" => "patient",
                        "errors" => $e
                    ];
                }
                if($patient->patient_id){
                    if(!empty($request['social_media'])) {
                        foreach($request['social_media'] as $s) {
                            $social_media = new PatientSocialMedia;
                            $social_media->patient_id = $patient->patient_id;
                            $social_media->social_media_id = $s['social_media_id'];
                            $social_media->user_link = $s['user_link'];
                            $social_media->n_of_follwer = $s['n_of_follwer'];
                            $social_media->created_by = Auth::id();
                            $social_media->updated_by = Auth::id();

                            try {
                                $social_media->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "patient_social_media",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['surgery_history'])) {
                        foreach($request['surgery_history'] as $s) {
                            $surgery_history = new SurgeryHistory;
                            $surgery_history->patient_id = $patient->patient_id;
                            $surgery_history->surgery_year = $s['surgery_year'];
                            $surgery_history->clinic_name = $s['clinic_name'];
                            $surgery_history->doctor_name = $s['doctor_name'];
                            $surgery_history->history_name = $s['history_name'];
                            $surgery_history->remark = $s['remark'];
                            $surgery_history->created_by = Auth::id();
                            $surgery_history->updated_by = Auth::id();
                            try {
                                $surgery_history->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "surgery_history",
                                    "errors" => $e
                                ];
                            }
                        }
                    }
                    // if(!empty($request['patient_case'])) {
                        $patient_case = new PatientCase;
                        $patient_case->patient_id   = $patient->patient_id;
                        $patient_case->procedure_id = $request['patient_case']['procedure_id'];
                        $patient_case->case_type_id = $request['patient_case']['case_type_id'];
                        $patient_case->case_group_id= $request['patient_case']['case_group_id'];
                        $patient_case->doctor_id    = $request['patient_case']['doctor_id'];
                        $patient_case->vn_no        = $request['patient_case']['vn_no'];
                        $patient_case->suggest_group= $request['patient_case']['suggest_group'];
                        $patient_case->suggested_by = $request['patient_case']['suggested_by'];
                        $patient_case->is_good_case = $request['patient_case']['is_good_case'];
                        $patient_case->is_bad_case  = $request['patient_case']['is_bad_case'];
                        $patient_case->is_good_review= $request['patient_case']['is_good_review'];
                        $patient_case->remark       = $request['patient_case']['remark'];
                        $patient_case->case_stage_id = 0;
                        $patient_case->status       = $request['patient_case']['status'];
                        $patient_case->created_by   = Auth::id();
                        $patient_case->updated_by   = Auth::id();
                        try {
                            $patient_case->save();
                            $current_patient_case_id = $patient_case->case_id;

                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "patient_case",
                                "errors" => $e
                            ];
                        }
                        if(!empty($request['patient_case']['supervised_by'])) {
                            $case_supervised = explode('|', $request['patient_case']['supervised_by']);
                            array_shift($case_supervised);
                            foreach($case_supervised as $s) {
                                $case_supervised = new CaseSupervised;

                                $case_supervised->case_id = $current_patient_case_id;
                                $case_supervised->supervised_id = $s;
                                $case_supervised->created_by = Auth::id();
                                try {
                                    // return $case_supervised;
                                    $case_supervised->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_supervised",
                                        "errors" => $e
                                    ];
                                }
                            }
                        }

                        $model_folder = Folder::where('is_template',1)
                                        ->with(['subFolder'=>function($qry){
                                                $qry->whereNOTNULL('folder_parent_id');
                                            }])
                                        ->get();
                        foreach ($model_folder as $folder) {
                            $case_folder = new CaseFolder();
                            $case_folder->case_id   =   $current_patient_case_id;
                            $case_folder->folder_id =   $folder->folder_id;
                            $case_folder->is_open   =   1;
                            $case_folder->is_pass   =   0;
                            $case_folder->created_by =   Auth::id();
                            $case_folder->save();
                            
                            $path_folder = public_path().'/uploads/ba/'.$patient->patient_id.'/'.$patient_case->case_id.'/file/'.$folder->folder_name;
                            if(!File::exists($path_folder)) @mkdir($path_folder, 0777, true);
                            
                            if(count($folder->subFolder) > 0){
                                foreach ($folder->subFolder as $subFolder) {
                                    $path_folder = public_path().'/uploads/ba/'.$patient->patient_id.'/'.$patient_case->case_id.'/file/'.$folder->folder_name.'/'.$subFolder->folder_name;
                                    if (!File::exists($path_folder)) @mkdir($path_folder, 0777, true);
                                }
                            } 
                        }

                    // }
                    
                    if(!empty($request['case_followup'])) {
                        foreach($request['case_followup'] as $s) {
                            $case_followup = new CaseFollowUp;
                            $case_followup->case_id = $current_patient_case_id;
                            $case_followup->procedure_id = $s['procedure_id'];
                            $case_followup->followup_year = $s['followup_year'];
                            $case_followup->remark = $s['remark'];
                            $case_followup->created_by = Auth::id();
                            $case_followup->updated_by = Auth::id();
                            try {
                                $case_followup->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_followup",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_price'])) {
                        foreach($request['case_price'] as $s) {
                            $case_price = new CasePrice;
                            $case_price->case_id = $current_patient_case_id;
                            $case_price->discount_type_id = $s['discount_type_id'];
                            $case_price->offer_price = $s['offer_price'];
                            $case_price->accept_price = $s['accept_price'];
                            $case_price->remark = $s['remark'];
                            $case_price->created_by = Auth::id();
                            $case_price->updated_by = Auth::id();
                            try {
                                $case_price->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_price",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_social_media'])) {
                        foreach($request['case_social_media'] as $s) {
                            $case_social_media = new CaseSocialMedia;
                            $case_social_media->case_id = $current_patient_case_id;
                            $case_social_media->social_media_id = $s['social_media_id'];
                            $case_social_media->link = $s['link'];
                            $case_social_media->usr_name = $s['usr_name'];
                            $case_social_media->pwd = $s['pwd'];
                            $case_social_media->note = $s['note'];
                            $case_social_media->created_by = Auth::id();
                            $case_social_media->updated_by = Auth::id();
                            try {
                                $case_social_media->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_social_media",
                                    "errors" => $e
                                ];
                            }
                        }
                    }
                
                    if(!empty($request['case_coordinate'])) {
                        foreach($request['case_coordinate'] as $s) {
                            $case_coordinate = new CaseCoordinate;
                            $case_coordinate->case_id = $current_patient_case_id;
                            $case_coordinate->usage_id = $s['usage_id'];
                            $case_coordinate->created_by = Auth::id();
                            try {
                                $case_coordinate->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_coordinate",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_appointment'])) {
                        foreach($request['case_appointment'] as $s) {
                            // return response()->json($s);
                            $case_appointment = new CaseAppointment;
                            $case_appointment->case_id              = $current_patient_case_id;
                            $case_appointment->appointment_type_id  = $s['appointment_type_id'];
                            $case_appointment->appointment_date     = $s['appointment_date'];
                            $case_appointment->doctor_id            = $s['doctor_id'];
                            $case_appointment->supervised_by        = $s['supervised_by']?null:$s['supervised_by'];
                            $case_appointment->is_vdo_product       = $s['is_vdo_product'];
                            $case_appointment->is_vdo_rh            = $s['is_vdo_rh'];
                            $case_appointment->is_picture_product   = $s['is_picture_product'];
                            $case_appointment->is_picture_rh        = $s['is_picture_rh'];
                            $case_appointment->is_meet_doctor       = $s['is_meet_doctor'];
                            $case_appointment->remark               = $s['remark'];
                            $case_appointment->created_by           = Auth::id();
                            $case_appointment->updated_by           = Auth::id();
                            try {
                                $case_appointment->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_appointment",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_contract'])) {
                        foreach($request['case_contract'] as $i => $s) {
                            if(empty($s['contract_id'])) {
                                //add new
                                $case_contract = new CaseContract;
                                $case_contract->case_id             = $current_patient_case_id;
                                $case_contract->procedure_id        = $s['procedure_id'];
                                $case_contract->contract_start_date = $s['contract_start_date'];
                                $case_contract->contract_end_date   = $s['contract_end_date'];
                                $case_contract->is_post             = $s['is_post'];
                                $case_contract->is_usage            = $s['is_usage'];
                                $case_contract->is_picture_vdo      = $s['is_picture_vdo'];
                                $case_contract->is_review           = $s['is_review'];
                                $case_contract->is_pr               = $s['is_pr'];
                                $case_contract->is_group_post       = $s['is_group_post'];
                                $case_contract->is_send_picture     = $s['is_send_picture'];
                                $case_contract->is_other            = $s['is_other'];
                                $case_contract->created_by          = Auth::id();
                                $case_contract->updated_by          = Auth::id();
                                try {
                                    $case_contract->save();
                                    $current_contract_id = $case_contract->contract_id;
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_contract",
                                        "errors" => $e,
                                        "type" => "add"
                                    ];
                                }

                                $result = array();
                                $path = '/uploads/ba/'.$patient->patient_id.'/'.$current_patient_case_id.'/contract/'. $current_contract_id ;
                                CaseContract::where('contract_id',$current_contract_id)->update(['contract_path'=>$path]);
                                $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;
                                if(!empty($filesData->file($s['file_name_check']))){
                                    foreach ($filesData->file($s['file_name_check']) as $file) {
                                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                        $file->move($fullPath,$filename);
                                        $item = new CaseContractDoc();
                                        $item->contract_path = $path.'/'.$filename;
                                        $item->contract_id = $current_contract_id;
                                        $item->created_by = Auth::id();
                                        try {
                                            $item->save();
                                            $result[] = $item;
                                        } catch (Exception $e) {
                                            $errors[] = [
                                                "table_name" => "case_contract_doc",
                                                "errors" => $e,
                                                "type" => "add"
                                            ];
                                        }
                                        
                                    }
                                }

                            } else {
                                //edit
                                $case_contract = CaseContract::find($s['contract_id']);
                                $case_contract->case_id         = $current_patient_case_id;
                                $case_contract->procedure_id    = $s['procedure_id'];
                                $case_contract->contract_start_date = $s['contract_start_date'];
                                $case_contract->contract_end_date = $s['contract_end_date'];
                                $case_contract->is_post         = $s['is_post'];
                                $case_contract->is_usage        = $s['is_usage'];
                                $case_contract->is_picture_vdo  = $s['is_picture_vdo'];
                                $case_contract->is_review       = $s['is_review'];
                                $case_contract->is_pr           = $s['is_pr'];
                                $case_contract->is_group_post   = $s['is_group_post'];
                                $case_contract->is_send_picture = $s['is_send_picture'];
                                $case_contract->is_other        = $s['is_other'];
                                $case_contract->updated_by      = Auth::id();
                                try {
                                    $case_contract->save();
                                    $current_contract_id = $case_contract->contract_id;
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_contract",
                                        "errors" => $e,
                                        "type" => "update"
                                    ];
                                }

                                if(!empty($filesData->file($s['file_name_check']))){

                                    $result = array();
                                    $path = $case_contract->contract_path;
                                    $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;
                                    foreach ($filesData->file($s['file_name_check']) as $file) {
                                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                        $file->move($fullPath,$filename);
                                        $item = new CaseContractDoc();
                                        $item->contract_path = $path.'/'.$filename;
                                        $item->contract_id = $current_contract_id;
                                        $item->created_by = Auth::id();
                                        try {
                                            $item->save();
                                            $result[] = $item;
                                        } catch (Exception $e) {
                                            $errors[] = [
                                                "table_name" => "case_contract_doc",
                                                "errors" => $e,
                                                "type" => "edit"
                                            ];
                                        }
                                        
                                    }
                                }
                            }
                        }
                    }

                    if(!empty($request['case_pr'])) {
                        foreach($request['case_pr'] as $s) {
                            $case_pr = new CasePR;
                            $case_pr->case_id           = $current_patient_case_id;
                            $case_pr->pr_id             = $s['pr_id'];
                            $case_pr->is_picture        = $s['is_picture'];
                            $case_pr->is_vdo            = $s['is_vdo'];
                            $case_pr->is_instragram     = $s['is_instragram'];
                            $case_pr->is_facebook       = $s['is_facebook'];
                            $case_pr->pr_plan_date      = $s['pr_plan_date'];
                            $case_pr->pr_actual_date    = $s['pr_actual_date'];
                            $case_pr->created_by = Auth::id();
                            $case_pr->updated_by = Auth::id();
                            try {
                                $case_pr->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_pr",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_article'])) {
                        foreach($request['case_article'] as $s) {
                            $case_article = new CaseArticle;
                            $case_article->case_id = $current_patient_case_id;
                            $case_article->article_name = $s['article_name'];
                            $case_article->article_type_id = $s['article_type_id'];
                            $case_article->writer               = $s['writer'];
                            $case_article->writing_start_date   = $s['writing_start_date'];
                            $case_article->writing_end_date     = $s['writing_end_date'];
                            $case_article->plan_date            = $s['plan_date'];
                            $case_article->created_by = Auth::id();
                            $case_article->updated_by = Auth::id();
                            try {
                                $case_article->save();
                                $current_case_article = $case_article->case_article_id;
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_article",
                                    "errors" => $e,
                                    "type" => "add"
                                ];
                            }
                            $result = array();
                            $path = '/uploads/ba/'.$patient->patient_id.'/'.$current_patient_case_id.'/article/'. $current_case_article ;
                            CaseArticle::where('case_article_id',$current_case_article)->update(['article_path'=>$path]);
                            $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;

                            if(!empty($filesData->file($s['file_name_check']))){
                                foreach ($filesData->file($s['file_name_check']) as $file) {
                                    // return response()->json($file);
                                    $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                    $file->move($fullPath,$filename);
                                    $item = new CaseArticleDoc();
                                    $item->case_article_id  = $current_case_article;
                                    $item->article_path     = $path.'/'.$filename;
                                    $item->created_by       = Auth::id();
                                    try {
                                        $item->save();
                                        $result[] = $item;
                                    } catch (Exception $e) {
                                        $errors[] = [
                                            "table_name" => "case_article_doc",
                                            "errors" => $e,
                                            "type" => "add"
                                        ];
                                    }
                                    
                                }
                            }
                        }
                    }             
            
                    if(!empty($request['case_stage'])) {
                        $case_stage = new CaseStage;
                        $case_stage->case_id        = $current_patient_case_id;
                        $case_stage->from_stage_id  = $request['case_stage']['from_stage_id'];
                        $case_stage->to_stage_id    = $request['case_stage']['to_stage_id'];
                        $case_stage->from_user_id   = $request['case_stage']['from_user_id'];
                        $case_stage->to_user_id     = $request['case_stage']['to_user_id'];
                        $case_stage->plan_date      = $request['case_stage']['plan_date'];
                        $case_stage->actual_date    = $request['case_stage']['actual_date'];
                        $case_stage->status         = $request['case_stage']['status'];
                        $case_stage->remark         = $request['case_stage']['remark'];
                        $case_stage->created_by     = Auth::id();
                        try {
                            $case_stage->save();
                            $current_case_stage = $case_stage->case_stage_id;

                            PatientCase::where('case_id',$current_patient_case_id)->update(['case_stage_id'=>$case_stage->case_stage_id]);
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "case_stage",
                                "errors" => $e
                            ];
                        } 


                        if($request['case_stage']['alerts']){
                            foreach ($request['case_stage']['alerts'] as $a) {
                                $alert = new CaseStageAlert;
                                $alert->case_stage_id = $current_case_stage;
                                $alert->user_id = $a;
                                $alert->created_by = Auth::id();
                                try {
                                    $alert->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "alert",
                                        "errors" => $e
                                    ];
                                }
                            }
                        } 

                        if($request['case_stage']['attach_img']){
                            foreach ($request['case_stage']['attach_img'] as $img) {
                                $model = new CaseStageDoc;
                                $model->case_stage_id = $current_case_stage;
                                $model->doc_path      = $img['image_path'];
                                $model->created_by    = Auth::id();
                                try {
                                    $model->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "attach_img",
                                        "errors" => $e
                                    ];
                                }
                            }
                        }

                        $result = array();

                        $path = '/uploads/ba/'.$patient->patient_id.'/'.$current_patient_case_id.'/stage/'. $current_case_stage ;
                        $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;
                        if(!empty($filesData->file('case_stage_upfile'))){
                            foreach ($filesData->file('case_stage_upfile') as $file) {
                                $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                $file->move($fullPath,$filename);

                                $item = new CaseStageDoc();
                                $item->case_stage_id = $current_case_stage;
                                $item->doc_path      = $path.'/'.$filename;
                                $item->created_by = Auth::id();
                                try {
                                    $item->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "item",
                                        "errors" => $e
                                    ];
                                }
                                $result[] = $item;              
                            } 
                        }
                    }
                }else{
                    return response()->json(['status'=>400]);
                }

            } else {
                //update
                try {
                    $patient = Patient::findOrFail($request['patient']['patient_id']);
                    $patient->hn_no             = $request['patient']['hn_no'];
                    $patient->patient_name      = $request['patient']['patient_name'];
                    $patient->nick_name         = $request['patient']['nick_name'];
                    $patient->birthday          = $request['patient']['birthday'];
                    $patient->nationality_id    = $request['patient']['nationality_id'];
                    $patient->gender            = $request['patient']['gender'];
                    $patient->id_card           = $request['patient']['id_card'];
                    $patient->home_no           = $request['patient']['home_no'];
                    $patient->moo               = $request['patient']['moo'];
                    $patient->soi               = $request['patient']['soi'];
                    $patient->road              = $request['patient']['road'];
                    $patient->province_id       = $request['patient']['province_id'];
                    $patient->amphur_id         = $request['patient']['amphur_id'];
                    $patient->district_id       = $request['patient']['district_id'];
                    $patient->mobile_no         = $request['patient']['mobile_no'];
                    $patient->telephone_no      = $request['patient']['telephone_no'];
                    $patient->career            = $request['patient']['career'];
                    $patient->updated_by        = Auth::id();
                     try {
                        $patient->save();
                        $current_patient_id = $patient->patient_id;

                        $file = $filesData->file('btnUpload_profileImage')[0];
                        $patient = Patient::find($patient->patient_id);
                        $path = '/uploads/ba/'.$patient->patient_id.'/profile_image';
                        if(!empty($file)) {
                            $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'.$path;
                            $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                            $file->move($fullPath,$filename); 
                            $patient->image_path = $path.'/'.$filename;
                        }
                        $patient->save();


                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "patient",
                            "errors" => $e
                        ];
                    }

                    // return response()->json($current_patient_id);
                    if(!empty($request['social_media'])) {
                        foreach($request['social_media'] as $s) {
                            if(empty($s['patient_media_id'])) {
                                //add new
                                $social_media = new PatientSocialMedia;
                                $social_media->patient_id = $patient->patient_id;
                                $social_media->social_media_id = $s['social_media_id'];
                                $social_media->user_link = $s['user_link'];
                                $social_media->n_of_follwer = $s['n_of_follwer'];
                                $social_media->created_by = Auth::id();
                                $social_media->updated_by = Auth::id();

                            } else {
                                //edit
                                $social_media = PatientSocialMedia::find($s['patient_media_id']);
                                $social_media->patient_id = $patient->patient_id;
                                $social_media->social_media_id = $s['social_media_id'];
                                $social_media->user_link = $s['user_link'];
                                $social_media->n_of_follwer = $s['n_of_follwer'];
                                $social_media->updated_by = Auth::id();
                            }
                            try {
                                $social_media->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "patient_social_media",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['surgery_history'])) {
                        foreach($request['surgery_history'] as $s) {
                            if(empty($s['history_id'])) {
                                //add new
                                $surgery_history = new SurgeryHistory;
                                $surgery_history->patient_id = $patient->patient_id;
                                $surgery_history->surgery_year = $s['surgery_year'];
                                $surgery_history->clinic_name = $s['clinic_name'];
                                $surgery_history->doctor_name = $s['doctor_name'];
                                $surgery_history->history_name = $s['history_name'];
                                $surgery_history->remark = $s['remark'];
                                $surgery_history->created_by = Auth::id();
                                $surgery_history->updated_by = Auth::id();

                            } else {
                                //edit
                                $surgery_history = SurgeryHistory::find($s['history_id']);
                                $surgery_history->patient_id = $patient->patient_id;
                                $surgery_history->surgery_year = $s['surgery_year'];
                                $surgery_history->clinic_name = $s['clinic_name'];
                                $surgery_history->doctor_name = $s['doctor_name'];
                                $surgery_history->history_name = $s['history_name'];
                                $surgery_history->remark = $s['remark'];
                                $surgery_history->updated_by = Auth::id();
                            }
                            try {
                                $surgery_history->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "surgery_history",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['patient_case']['case_id'])) {
                        //update
                        $patient_case = PatientCase::find($request['patient_case']['case_id']);
                        $patient_case->patient_id   = $request['patient_case']['patient_id'];
                        $patient_case->procedure_id = $request['patient_case']['procedure_id'];
                        $patient_case->case_type_id = $request['patient_case']['case_type_id'];
                        $patient_case->case_group_id= $request['patient_case']['case_group_id'];
                        $patient_case->doctor_id    = $request['patient_case']['doctor_id'];
                        $patient_case->vn_no        = $request['patient_case']['vn_no'];
                        $patient_case->suggest_group= $request['patient_case']['suggest_group'];
                        $patient_case->suggested_by = $request['patient_case']['suggested_by'];
                        $patient_case->is_good_case = $request['patient_case']['is_good_case'];
                        $patient_case->is_bad_case  = $request['patient_case']['is_bad_case'];
                        $patient_case->is_good_review= $request['patient_case']['is_good_review'];
                        $patient_case->remark       = $request['patient_case']['remark'];
                        $patient_case->status       = $request['patient_case']['status'];
                        $patient_case->updated_by   = Auth::id();
                        try {
                            $patient_case->save();
                            $current_patient_case_id = $patient_case->case_id;

                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "patient_case",
                                "errors" => $e
                            ];
                        }

                    } else {
                        // add
                        $patient_case = new PatientCase;
                        $patient_case->patient_id   = $patient->patient_id;
                        $patient_case->procedure_id = $request['patient_case']['procedure_id'];
                        $patient_case->case_type_id = $request['patient_case']['case_type_id'];
                        $patient_case->case_group_id= $request['patient_case']['case_group_id'];
                        $patient_case->doctor_id    = $request['patient_case']['doctor_id'];
                        $patient_case->vn_no        = $request['patient_case']['vn_no'];
                        $patient_case->suggest_group= $request['patient_case']['suggest_group'];
                        $patient_case->suggested_by = $request['patient_case']['suggested_by'];
                        $patient_case->is_good_case = $request['patient_case']['is_good_case'];
                        $patient_case->is_bad_case  = $request['patient_case']['is_bad_case'];
                        $patient_case->is_good_review= $request['patient_case']['is_good_review'];
                        $patient_case->remark       = $request['patient_case']['remark'];
                        $patient_case->case_stage_id= 0;
                        $patient_case->status       = $request['patient_case']['status'];
                        $patient_case->created_by = Auth::id();
                        $patient_case->updated_by = Auth::id();
                        try {
                            $patient_case->save();
                            $current_patient_case_id = $patient_case->case_id;

                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "patient_case",
                                "errors" => $e
                            ];
                        }

                        $model_folder = Folder::where('is_template',1)
                                        ->with(['subFolder'=>function($qry){
                                                $qry->whereNOTNULL('folder_parent_id');
                                            }])
                                        ->get();
                        foreach ($model_folder as $folder) {
                            $case_folder = new CaseFolder();
                            $case_folder->case_id   =   $current_patient_case_id;
                            $case_folder->folder_id =   $folder->folder_id;
                            $case_folder->is_open   =   1;
                            $case_folder->is_pass   =   0;
                            $case_folder->created_by =   Auth::id();
                            $case_folder->save();
                            
                            $path_folder = public_path().'/uploads/ba/'.$patient->patient_id.'/'.$patient_case->case_id.'/file/'.$folder->folder_name;
                            if(!File::exists($path_folder)) @mkdir($path_folder, 0777, true);
                            
                            if(count($folder->subFolder) > 0){
                                foreach ($folder->subFolder as $subFolder) {
                                    $path_folder = public_path().'/uploads/ba/'.$patient->patient_id.'/'.$patient_case->case_id.'/file/'.$folder->folder_name.'/'.$subFolder->folder_name;
                                    if (!File::exists($path_folder)) @mkdir($path_folder, 0777, true);
                                }
                            } 
                        }
                    }

                    CaseSupervised::where('case_id', '=', $current_patient_case_id)->delete();
                    $case_supervised = explode('|', $request['patient_case']['supervised_by']);
                    array_shift($case_supervised);
                    foreach($case_supervised as $s) {
                        $case_supervised = new CaseSupervised;
                        $case_supervised->case_id = $current_patient_case_id;
                        $case_supervised->supervised_id = $s;
                        $case_supervised->created_by = Auth::id();
                        try {
                            $case_supervised->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "case_supervised",
                                "errors" => $e
                            ];
                        }
                    }

                    if(!empty($request['case_followup'])) {
                        foreach($request['case_followup'] as $s) {
                            if(empty($s['followup_id'])) {
                                //add new
                                $case_followup = new CaseFollowUp;
                                $case_followup->case_id = $current_patient_case_id;
                                $case_followup->procedure_id = $s['procedure_id'];
                                $case_followup->followup_year = $s['followup_year'];
                                $case_followup->remark = $s['remark'];
                                $case_followup->created_by = Auth::id();
                                $case_followup->updated_by = Auth::id();

                            } else {
                                //edit
                                $case_followup = CaseFollowUp::find($s['followup_id']);
                                $case_followup->case_id = $current_patient_case_id;
                                $case_followup->procedure_id = $s['procedure_id'];
                                $case_followup->followup_year = $s['followup_year'];
                                $case_followup->remark = $s['remark'];
                                $case_followup->updated_by = Auth::id();
                            }
                            try {
                                $case_followup->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_followup",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_price'])) {
                        foreach($request['case_price'] as $s) {
                            if(empty($s['price_id'])) {
                                //add new
                                $case_price = new CasePrice;
                                $case_price->case_id = $current_patient_case_id;
                                $case_price->discount_type_id = $s['discount_type_id'];
                                $case_price->offer_price = $s['offer_price'];
                                $case_price->accept_price = $s['accept_price'];
                                $case_price->remark = $s['remark'];
                                $case_price->created_by = Auth::id();
                                $case_price->updated_by = Auth::id();

                            } else {
                                //edit
                                $case_price = CasePrice::find($s['price_id']);
                                $case_price->case_id = $current_patient_case_id;
                                $case_price->discount_type_id = $s['discount_type_id'];
                                $case_price->offer_price = $s['offer_price'];
                                $case_price->accept_price = $s['accept_price'];
                                $case_price->remark = $s['remark'];
                                $case_price->updated_by = Auth::id();
                            }
                            try {
                                $case_price->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_price",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_social_media'])) {
                        foreach($request['case_social_media'] as $s) {
                            if(empty($s['case_media_id'])) {
                                //add new
                                $case_social_media = new CaseSocialMedia;
                                $case_social_media->case_id = $current_patient_case_id;
                                $case_social_media->social_media_id = $s['social_media_id'];
                                $case_social_media->link = $s['link'];
                                $case_social_media->usr_name = $s['usr_name'];
                                $case_social_media->pwd = $s['pwd'];
                                $case_social_media->note = $s['note'];
                                $case_social_media->created_by = Auth::id();
                                $case_social_media->updated_by = Auth::id();

                            } else {
                                //edit
                                $case_social_media = CaseSocialMedia::find($s['case_media_id']);
                                $case_social_media->case_id = $current_patient_case_id;
                                $case_social_media->social_media_id = $s['social_media_id'];
                                $case_social_media->link = $s['link'];
                                $case_social_media->usr_name = $s['usr_name'];
                                $case_social_media->pwd = $s['pwd'];
                                $case_social_media->note = $s['note'];
                                $case_social_media->updated_by = Auth::id();
                            }
                            try {
                                $case_social_media->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_social_media",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_coordinate'])) {
                        CaseCoordinate::where('case_id', '=', $current_patient_case_id)->delete();
                        foreach($request['case_coordinate'] as $s) {
                            $case_coordinate = new CaseCoordinate;
                            $case_coordinate->case_id = $current_patient_case_id;
                            $case_coordinate->usage_id = $s['usage_id'];
                            $case_coordinate->created_by = Auth::id();
                            try {
                                $case_coordinate->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_coordinate",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_appointment'])) {
                        foreach($request['case_appointment'] as $s) {
                            if(empty($s['appointment_id'])) {
                                //add new
                                $case_appointment = new CaseAppointment;
                                $case_appointment->case_id = $current_patient_case_id;
                                $case_appointment->appointment_type_id = $s['appointment_type_id'];
                                $case_appointment->appointment_date = $s['appointment_date'];
                                $case_appointment->doctor_id = $s['doctor_id'];
                                $case_appointment->supervised_by = $s['supervised_by'];
                                $case_appointment->is_vdo_product = $s['is_vdo_product'];
                                $case_appointment->is_vdo_rh = $s['is_vdo_rh'];
                                $case_appointment->is_picture_product = $s['is_picture_product'];
                                $case_appointment->is_picture_rh = $s['is_picture_rh'];
                                $case_appointment->is_meet_doctor = $s['is_meet_doctor'];
                                $case_appointment->remark = $s['remark'];
                                $case_appointment->created_by = Auth::id();
                                $case_appointment->updated_by = Auth::id();

                            } else {
                                //edit
                                $case_appointment = CaseAppointment::find($s['appointment_id']);
                                $case_appointment->case_id = $current_patient_case_id;
                                $case_appointment->appointment_type_id = $s['appointment_type_id'];
                                $case_appointment->appointment_date = $s['appointment_date'];
                                $case_appointment->doctor_id = $s['doctor_id'];
                                $case_appointment->supervised_by = $s['supervised_by'];
                                $case_appointment->is_vdo_product = $s['is_vdo_product'];
                                $case_appointment->is_vdo_rh = $s['is_vdo_rh'];
                                $case_appointment->is_picture_product = $s['is_picture_product'];
                                $case_appointment->is_picture_rh = $s['is_picture_rh'];
                                $case_appointment->is_meet_doctor = $s['is_meet_doctor'];
                                $case_appointment->remark = $s['remark'];
                                $case_appointment->updated_by = Auth::id();
                            }
                            try {
                                $case_appointment->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_appointment",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_contract'])) {
                        foreach($request['case_contract'] as $i => $s) {
                            if(empty($s['contract_id'])) {
                                //add new
                                $case_contract = new CaseContract;
                                $case_contract->case_id             = $current_patient_case_id;
                                $case_contract->procedure_id        = $s['procedure_id'];
                                $case_contract->contract_start_date = $s['contract_start_date'];
                                $case_contract->contract_end_date   = $s['contract_end_date'];
                                $case_contract->is_post             = $s['is_post'];
                                $case_contract->is_usage            = $s['is_usage'];
                                $case_contract->is_picture_vdo      = $s['is_picture_vdo'];
                                $case_contract->is_review           = $s['is_review'];
                                $case_contract->is_pr               = $s['is_pr'];
                                $case_contract->is_group_post       = $s['is_group_post'];
                                $case_contract->is_send_picture     = $s['is_send_picture'];
                                $case_contract->is_other            = $s['is_other'];
                                $case_contract->created_by          = Auth::id();
                                $case_contract->updated_by          = Auth::id();
                                try {
                                    $case_contract->save();
                                    $current_contract_id = $case_contract->contract_id;
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_contract",
                                        "errors" => $e,
                                        "type" => "add"
                                    ];
                                }

                                $result = array();
                                $path = '/uploads/ba/'.$patient->patient_id.'/'.$current_patient_case_id.'/contract/'. $current_contract_id ;
                                CaseContract::where('contract_id',$current_contract_id)->update(['contract_path'=>$path]);
                                $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;
                                if(!empty($filesData->file($s['file_name_check']))){
                                    foreach ($filesData->file($s['file_name_check']) as $file) {
                                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                        $file->move($fullPath,$filename);
                                        $item = new CaseContractDoc();
                                        $item->contract_path = $path.'/'.$filename;
                                        $item->contract_id = $current_contract_id;
                                        $item->created_by = Auth::id();
                                        try {
                                            $item->save();
                                            $result[] = $item;
                                        } catch (Exception $e) {
                                            $errors[] = [
                                                "table_name" => "case_contract_doc",
                                                "errors" => $e,
                                                "type" => "add"
                                            ];
                                        }
                                        
                                    }
                                }

                            } else {
                                //edit
                                $case_contract = CaseContract::find($s['contract_id']);
                                $case_contract->case_id         = $current_patient_case_id;
                                $case_contract->procedure_id    = $s['procedure_id'];
                                $case_contract->contract_start_date = $s['contract_start_date'];
                                $case_contract->contract_end_date = $s['contract_end_date'];
                                $case_contract->is_post         = $s['is_post'];
                                $case_contract->is_usage        = $s['is_usage'];
                                $case_contract->is_picture_vdo  = $s['is_picture_vdo'];
                                $case_contract->is_review       = $s['is_review'];
                                $case_contract->is_pr           = $s['is_pr'];
                                $case_contract->is_group_post   = $s['is_group_post'];
                                $case_contract->is_send_picture = $s['is_send_picture'];
                                $case_contract->is_other        = $s['is_other'];
                                $case_contract->updated_by      = Auth::id();
                                try {
                                    $case_contract->save();
                                    $current_contract_id = $case_contract->contract_id;
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_contract",
                                        "errors" => $e,
                                        "type" => "update"
                                    ];
                                }

                                if(!empty($filesData->file($s['file_name_check']))){

                                    $result = array();
                                    $path = $case_contract->contract_path;
                                    $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;
                                    foreach ($filesData->file($s['file_name_check']) as $file) {
                                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                        $file->move($fullPath,$filename);
                                        $item = new CaseContractDoc();
                                        $item->contract_path = $path.'/'.$filename;
                                        $item->contract_id = $current_contract_id;
                                        $item->created_by = Auth::id();
                                        try {
                                            $item->save();
                                            $result[] = $item;
                                        } catch (Exception $e) {
                                            $errors[] = [
                                                "table_name" => "case_contract_doc",
                                                "errors" => $e,
                                                "type" => "edit"
                                            ];
                                        }
                                        
                                    }
                                }
                            }
                        }
                    }

                    if(!empty($request['case_pr'])) {
                        foreach($request['case_pr'] as $s) {
                            if(empty($s['case_pr_id'])) {
                                //add new
                                $case_pr = new CasePR;
                                $case_pr->case_id = $current_patient_case_id;
                                $case_pr->pr_id = $s['pr_id'];
                                $case_pr->is_picture = $s['is_picture'];
                                $case_pr->is_vdo = $s['is_vdo'];
                                $case_pr->is_instragram = $s['is_instragram'];
                                $case_pr->is_facebook = $s['is_facebook'];
                                $case_pr->pr_plan_date = $s['pr_plan_date'];
                                $case_pr->pr_actual_date = $s['pr_actual_date'];
                                $case_pr->created_by = Auth::id();
                                $case_pr->updated_by = Auth::id();

                            } else {
                                //edit
                                $case_pr = CasePR::find($s['case_pr_id']);
                                $case_pr->case_id = $current_patient_case_id;
                                $case_pr->pr_id = $s['pr_id'];
                                $case_pr->is_picture = $s['is_picture'];
                                $case_pr->is_vdo = $s['is_vdo'];
                                $case_pr->is_instragram = $s['is_instragram'];
                                $case_pr->is_facebook = $s['is_facebook'];
                                $case_pr->pr_plan_date = $s['pr_plan_date'];
                                $case_pr->pr_actual_date = $s['pr_actual_date'];
                                $case_pr->updated_by = Auth::id();
                            }
                            try {
                                $case_pr->save();
                            } catch (Exception $e) {
                                $errors[] = [
                                    "table_name" => "case_pr",
                                    "errors" => $e
                                ];
                            }
                        }
                    }

                    if(!empty($request['case_article'])) {
                        foreach($request['case_article'] as $s) {
                            if(empty($s['case_article_id'])) {
                                //add new
                                $case_article = new CaseArticle;
                                $case_article->case_id = $current_patient_case_id;
                                $case_article->article_name = $s['article_name'];
                                $case_article->article_type_id = $s['article_type_id'];
                                $case_article->writer = $s['writer'];
                                $case_article->writing_start_date = $s['writing_start_date'];
                                $case_article->writing_end_date = $s['writing_end_date'];
                                $case_article->plan_date = $s['plan_date'];
                                $case_article->article_path = "/master_piece/public/case_article/";
                                $case_article->created_by = Auth::id();
                                $case_article->updated_by = Auth::id();
                                try {
                                    $case_article->save();
                                    $current_case_article = $case_article->case_article_id;
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_article",
                                        "errors" => $e,
                                        "type" => "add"
                                    ];
                                }

                                $result = array();
                                $path = '/uploads/ba/'.$patient->patient_id.'/'.$current_patient_case_id.'/article/'. $current_case_article ;
                                CaseArticle::where('case_article_id',$current_case_article)->update(['article_path'=>$path]);
                                $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;

                                if(!empty($filesData->file($s['file_name_check']))){
                                    foreach ($filesData->file($s['file_name_check']) as $file) {
                                        // return response()->json($file);
                                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                        $file->move($fullPath,$filename);
                                        $item = new CaseArticleDoc();
                                        $item->case_article_id  = $current_case_article;
                                        $item->article_path     = $path.'/'.$filename;
                                        $item->created_by       = Auth::id();
                                        try {
                                            $item->save();
                                            $result[] = $item;
                                        } catch (Exception $e) {
                                            $errors[] = [
                                                "table_name" => "case_article_doc",
                                                "errors" => $e,
                                                "type" => "add"
                                            ];
                                        }
                                        
                                    }
                                }

                            } else {
                                //edit
                                $case_article = CaseArticle::find($s['case_article_id']);
                                $case_article->case_id          = $current_patient_case_id;
                                $case_article->article_name     = $s['article_name'];
                                $case_article->article_type_id  = $s['article_type_id'];
                                $case_article->writer           = $s['writer'];
                                $case_article->writing_start_date = $s['writing_start_date'];
                                $case_article->writing_end_date = $s['writing_end_date'];
                                $case_article->plan_date        = $s['plan_date'];
                                $case_article->updated_by       = Auth::id();
                                try {
                                    $case_article->save();
                                    $current_case_article = $case_article->case_article_id;
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "case_article",
                                        "errors" => $e,
                                        "type" => "update"
                                    ];
                                }       
                                $result = array();
                                $path = $case_article->article_path ;
                                $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;

                                if(!empty($filesData->file($s['file_name_check']))){
                                    foreach ($filesData->file($s['file_name_check']) as $file) {
                                        // return response()->json($file);
                                        $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                        $file->move($fullPath,$filename);
                                        $item = new CaseArticleDoc();
                                        $item->case_article_id  = $current_case_article;
                                        $item->article_path     = $path.'/'.$filename;
                                        $item->created_by       = Auth::id();
                                        try {
                                            $item->save();
                                            $result[] = $item;
                                        } catch (Exception $e) {
                                            $errors[] = [
                                                "table_name" => "case_article_doc",
                                                "errors" => $e,
                                                "type" => "edit"
                                            ];
                                        }
                                        
                                    }
                                }
                            }
                        }
                    }

                    if(!empty($request['case_stage'])) {
                        // return $request['case_stage']['case_id'];
                        $case_stage = new CaseStage;
                        $case_stage->case_id        = $current_patient_case_id;
                        $case_stage->from_stage_id  = $request['case_stage']['from_stage_id'];
                        $case_stage->to_stage_id    = $request['case_stage']['to_stage_id'];
                        $case_stage->from_user_id   = $request['case_stage']['from_user_id'];
                        $case_stage->to_user_id     = $request['case_stage']['to_user_id'];
                        $case_stage->plan_date      = $request['case_stage']['plan_date'];
                        $case_stage->actual_date    = $request['case_stage']['actual_date'];
                        $case_stage->status         = $request['case_stage']['status'];
                        $case_stage->remark         = $request['case_stage']['remark'];
                        $case_stage->created_by     = Auth::id();
                        try {
                            $case_stage->save();
                            $current_case_stage = $case_stage->case_stage_id;
                            PatientCase::where('case_id',$current_patient_case_id)->update(['case_stage_id'=>$case_stage->case_stage_id]);
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "case_stage",
                                "errors" => $e
                            ];
                        }  
                        if($request['case_stage']['alerts']){
                            foreach ($request['case_stage']['alerts'] as $a) {
                                $alert = new CaseStageAlert;
                                $alert->case_stage_id = $current_case_stage;
                                $alert->user_id = $a;
                                $alert->created_by = Auth::id();
                                try {
                                    $alert->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "alert",
                                        "errors" => $e
                                    ];
                                }
                            }
                        } 

                        if($request['case_stage']['attach_img']){
                            foreach ($request['case_stage']['attach_img'] as $img) {
                                $model = new CaseStageDoc;
                                $model->case_stage_id = $current_case_stage;
                                $model->doc_path      = $img['image_path'];
                                $model->created_by    = Auth::id();
                                try {
                                    $model->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "attach_img",
                                        "errors" => $e
                                    ];
                                }
                            }
                        }

                        $result = array();
                        $path = '/uploads/ba/'.$patient->patient_id.'/'.$current_patient_case_id.'/stage/'. $current_case_stage ;
                        $fullPath = $_SERVER['DOCUMENT_ROOT'].'/master_piece/public'. $path;
                        if(!empty($filesData->file('case_stage_upfile'))){
                            foreach ($filesData->file('case_stage_upfile') as $file) {
                                $filename = date('YmdHis').'_'.iconv('UTF-8','windows-874',$file->getClientOriginalName());
                                $file->move($fullPath,$filename);
                                $item = new CaseStageDoc();
                                $item->case_stage_id = $current_case_stage;
                                $item->doc_path      = $path.'/'.$filename;
                                $item->created_by = Auth::id();
                                try {
                                    $item->save();
                                } catch (Exception $e) {
                                    $errors[] = [
                                        "table_name" => "item",
                                        "errors" => $e
                                    ];
                                }
                                $result[] = $item;              
                            } 
                        } 
                    }
                
                }catch (ModelNotFoundException $e) {
                    return response()->json(['status' => 400, 'errors' => 'Patient not found.']);
                }
            }
        }

        empty($errors) ? DB::commit() : DB::rollback();

        /* send mail*/
        if(empty($errors)){
            if($request['case_stage']['to_user_id']){
                $toUser     = $request['case_stage']['to_user_id'];
                $user_mail  = [$request['case_stage']['to_user_id']];
                if($request['case_stage']['alerts']){ 
                    $user_mail = array_replace([$case_stage->to_user_id],$request['case_stage']['alerts']);
                }
                try {
                    foreach ($user_mail as $user) {
                        $model = CaseStage::where('case_stage_id',$current_case_stage)
                                ->with(['fromUser'=>function($qry){
                                    $qry->select(['emailAddress','screenName','userId']);
                                },'toUser'=>function($qry){
                                    $qry->select(['emailAddress','screenName','userId']);
                                },'fromStage','toStage',
                                'patientCase'=>function($qry){
                                    $qry->select(['vn_no','case_id','patient_id','procedure_id']);
                                },'patientCase.patient'=>function($qry){
                                    $qry->select(['hn_no','patient_id','patient_name']);
                                },'patientCase.procedure'=>function($qry){
                                    $qry->select(['procedure_name','procedure_id']);
                                }
                            ])->first() ;
                        $data = [
                            "to_user"           => $model->fromUser->emailAddress, 
                            "vn_no"             => $model->patientCase->vn_no,
                            "patient_name"      => $model->patientCase->patient->patient_name,
                            "hn_no"             => $model->patientCase->patient->hn_no,
                            "procedure_name"    => $model->patientCase->procedure->procedure_name,
                            "from_stage_name"   => $model->fromStage->stage_name,
                            "to_stage_name"     => $model->toStage->stage_name,
                            "status"            => $model->status,
                            "pic"               => $model->toUser->screenName
                        ];

                        $from = 'gjtestmail2017@gmail.com';
                        $to = $model->fromUser->emailAddress;
                        Mail::send('emails.ba', $data, function($message) use ($from, $to) {
                            $message->from($from, 'Review Hunter');
                            $message->to($to)->subject('แจ้งเตือน ระบบ Review Hunter');
                        });

                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }
    
    public function destroy_social_media(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                PatientSocialMedia::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_surgery_history(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                SurgeryHistory::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_case_price(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                CasePrice::find($edu)->delete();
            }
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_case_social_media(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                CaseSocialMedia::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_case_appointment(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                CaseAppointment::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_case_contract(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                CaseContract::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_case_pr(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                CasePR::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }

    public function destroy_case_article(Request $req){
        try {
            $arr = json_decode($req->arr);
            foreach ($arr as $edu) {
                CaseArticle::find($edu)->delete();
            } 
            return response()->json(['status' => 200, 'data' => $req->arr]);
        }catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
        }
    }


    public function section_role(request $req) {   
        
        $user = DB::table('lportal.users_roles')->select('roleId')->where('userId',Auth::user()->userId)->get();
        if (empty($user)) {
            return response()->json("No role assigned for current user.");
        }
        $role = [];
        foreach ($user as $key => $value) {
            array_push($role, $value->roleId);
        }
        
        $data['section'] = DB::table('section_authorize')
            ->select('section_id',DB::raw("max(add_flag) as add_flag ,max(edit_flag) as edit_flag,max(delete_flag) as delete_flag ,max(upload_flag) as upload_flag,max(download_flag) as download_flag"))->whereIn('roleId',$role)->groupBy('section_id')->get();
        

        $data['userRole'] = db::table('lportal.user_ as usr')
                                ->join('lportal.users_roles as rol', 'usr.userId', '=', 'rol.userId')
                                ->select('rol.roleId as roleId')
                                ->where('usr.userId',Auth::user()->userId)->lists('roleId');

        $data['stageRole'] = Stage::where('stage_id',$req->stage_id)->first();

        return response()->json($data);
    }   

}