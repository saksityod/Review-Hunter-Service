<?php

namespace App\Http\Controllers;

use App\Article;
use App\ArticleDoc;
use App\CaseStage;
use App\ArticleStage; // waiting code to_user_id
use App\ArticleStageDoc;
use App\ArticleStageAlert; // waiting code to_user_id

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
use ZipArchive;

class WriterController extends Controller {
    
    public function __construct() {
        //$this->middleware('jwt.auth');
    }

    public function list_article(Request $request) {
        $items = DB::select("
            SELECT article_ID, article_name
            from article
            where article_name
            like '%{$request->article_name}%'
        ");
        return response()->json($items);
    }

    public function list_medical_procedure(Request $request) {
        $items = DB::select("
            SELECT m.procedure_id, m.procedure_name
            from medical_procedure m
            inner join article a on m.procedure_id = a.procedure_id
        ");
        return response()->json($items);
    }

    public function list_doctor_article(Request $request) {
        $items = DB::select("
            SELECT d.doctor_id, d.doctor_name
            from doctor d
            inner join article a on d.doctor_id = a.doctor_id
            where d.doctor_name like '%{$request->doctor_name}%'
        ");
        return response()->json($items);
    }

    public function list_doctor(Request $request) {
        $items = DB::select("
            SELECT doctor_id, doctor_name
            from doctor
            where is_active = 1
            and doctor_name like '%{$request->doctor_name}%'
        ");
        return response()->json($items);
    }

    public function list_article_type(Request $request) {
        $items = DB::select("
            SELECT article_type_id, article_type_name
            from article_type
        ");
        return response()->json($items);
    }

    // public function search(Request $request)
    // {
    //     $qinput = array();
    //     $query = "
    //         select pt.patient_name as patient_name, mp.procedure_name as procedure_name,  concat(us.firstName, ' ', us.lastName) as pic_name, 
    //         t_s.stage_name as task_name, cs.status as status, cs.plan_date as plan_date, cs.actual_date as_actual_date, dr.doctor_name as doctor_name
    //         from patient pt, patient_case pc, medical_procedure mp , case_stage cs, doctor dr, lportal.user_ us, workflow_stage pwf,  workflow_stage cwf, stage t_s
    //         where pt.patient_id = pc.patient_id
    //         and pc.case_id = cs.case_id
    //         and pc.procedure_id = mp.procedure_id
    //         and pc.doctor_id = dr.doctor_id
    //         and cs.to_user_id = us.userId
    //         and pc.workflow_stage_id = pwf.workflow_stage_id
    //         and cs.workflow_stage_id = cwf.workflow_stage_id
    //         and cwf.to_stage_id = t_s.stage_id          
    //     ";
        
    //     empty($request->patient_id) ?: ($query .= " and pt.patient_id = ? " AND $qinput[] = $request->patient_id);
    //     empty($request->status) ?: ($query .= " and cwf.status = ? " AND $qinput[] = $request->status);
    //     empty($request->procedure_id) ?: ($query .= " and pc.procedure_id = ? " AND $qinput[] = $request->procedure_id);
    //     empty($request->doctor_id) ?: ($query .= " pc.doctor_id = ? " AND $qinput[] = $request->doctor_id);
    //     empty($request->user_id) ?: ($query .= " and cs.to_user_id  = ? " AND $qinput[] = $request->user_id);
            
    //     $query .= "
    //         union 
    //         select 'N/A' as patient_name, mp.procedure_name as procedure_name, concat(us.firstName, ' ', us.lastName) as pic_name, 
    //         t_s.stage_name as task_name, ars.status as status, ars.plan_date as plan_date, ars.actual_date as_actual_date, dr.doctor_name as doctor_name
    //         from article ac, medical_procedure mp, article_stage ars, doctor dr, lportal.user_ us, workflow_stage awf, workflow_stage aswf, stage t_s
    //         where ac.article_id = ars.article_id
    //         and ac.procedure_id = mp.procedure_id
    //         and ac.doctor_id = dr.doctor_id
    //         and ars.to_user_id = us.userId
    //         and ac.workflow_stage_id = awf.workflow_stage_id
    //         and ars.workflow_stage_id = aswf.workflow_stage_id
    //         and aswf.to_stage_id = t_s.stage_id
    //     ";
    //     empty($request->status) ?: ($query .= " and aswf.status = ? " AND $qinput[] = $request->status);
    //     empty($request->procedure_id) ?: ($query .= " and ac.procedure_id = ? " AND $qinput[] = $request->procedure_id);
    //     empty($request->doctor_id) ?: ($query .= " ac.doctor_id = ? " AND $qinput[] = $request->doctor_id);
    //     empty($request->user_id) ?: ($query .= " and as.to_user_id  = ? " AND $qinput[] = $request->user_id);   

    //     $items = DB::select($query,$qinput);
        
    //     // Get the current page from the url if it's not set default to 1
    //     empty($request->page) ? $page = 1 : $page = $request->page;
        
    //     // Number of items per page
    //     empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
        
    //     $offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

    //     // Get only the items you need using array_slice (only get 10 items since that's what you need)
    //     $itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
        
    //     // Return the paginator with only 10 items but with the count of all items and set the it on the correct page
    //     $result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);               
        
    //     return response()->json($result);
        
    // }

    public function search_writer(Request $request) {
        $article_id = (empty($request->article_id)) ? "like '%%'" : "= '{$request->article_id}'";
        $procedure_id = (empty($request->procedure_id)) ? "like '%%'" : "= '{$request->procedure_id}'";
        $doctor_id = (empty($request->doctor_id)) ? "like '%%'" : "= '{$request->doctor_id}'";

        $items = DB::select("
            SELECT a.article_id,
                    a.article_name,
                    a.writer,
                    a.writing_start_date,
                    a.writing_end_date,
                    a.plan_date,
                    a.status,
                    at.article_type_name,
                    p.procedure_name,
                    d.doctor_name
            from article a
            inner join article_type at on at.article_type_id = a.article_type_id
            inner join medical_procedure p on p.procedure_id = a.procedure_id
            inner join doctor d on d.doctor_id = a.doctor_id
            where a.article_id $article_id
            and a.procedure_id $procedure_id
            and a.doctor_id $doctor_id
            and a.writing_start_date between '{$request->writing_start_date}' and '{$request->writing_end_date}'
        ");
        return response()->json($items);
        
        // Get the current page from the url if it's not set default to 1
        // empty($request->page) ? $page = 1 : $page = $request->page;
        
        // // Number of items per page
        // empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
        
        // $offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

        // // Get only the items you need using array_slice (only get 10 items since that's what you need)
        // $itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
        
        // // Return the paginator with only 10 items but with the count of all items and set the it on the correct page
        // $result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);               
        
        // return response()->json($result);
    }

    public function action_to(Request $request)
    {
        $workflow_stage = DB::select("
            select to_stage_id, status
            from workflow_stage
            where from_stage_id = ?
        ",array($request->stage_id));
        
        if (empty($workflow_stage)) {
            return response()->json([]);
        }
        
        $actions = DB::select(" 
            select stage_id, stage_name, '{$workflow_stage[0]->status}' status
            from stage
            where stage_id in ({$workflow_stage[0]->to_stage_id})
            order by stage_id
        "); 
        
        return response()->json($actions);      
    }

    public function cu(Request $filesdata) {
        // foreach ($filesdata->file() as $key => $value) {
        //     $keye = explode('-', $key);
        //     if($keye[0]=='article_doc') {
        //         return response()->json($keye[0]);
        //     }
        // }
        // return response()->json($filesdata->file());

        $request = (array)json_decode($filesdata->formdata);
        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator_article = Validator::make($request, [
            'to_stage_id' => 'required|integer',
            'article_name' => 'required|max:256',
            'article_type_id' => 'required|integer',
            'procedure_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            'from_user_id' => 'required|integer',
            'writing_start_date' => 'required|date|date_format:Y-m-d',
            'writing_end_date' => 'required|date|date_format:Y-m-d',
            'plan_date' => 'required|date|date_format:Y-m-d',
        ]);
        if($validator_article->fails()){$errors_validator[] = $validator_article->errors();}

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }

        if(empty($request->article_id)) {
            //add
            $article = new Article;
            $article->stage_id = $request->to_stage_id;
            $article->article_name = $request->article_name;
            $article->article_type_id = $request->article_type_id;
            $article->procedure_id = $request->procedure_id;
            $article->doctor_id = $request->doctor_id;
            $article->writer = $request->from_user_id;
            $article->writing_start_date = $request->writing_start_date;
            $article->writing_end_date = $request->writing_end_date;
            $article->plan_date = $request->plan_date;
            $article->article_path = "/master_piece/public/article/";
            $article->created_by = Auth::id();
            $article->updated_by = Auth::id();
            try {
                $article->save();
                $current_article_id = $article->article_id;
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article",
                    "errors" => $e
                ];
            }

            foreach ($filesdata->file() as $key => $f) {
                $keye = explode('-', $key);
                if($keye[0]=='article_doc') {
                    $result = array();
                    $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/article/' . $current_article_id . '/';
                    $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                    $f->move($path,$filename);
                    $item = ArticleDoc::firstOrNew(array('article_id' => $current_article_id, 'doc_path' => 'article/' . $current_article_id . '/' . $f->getClientOriginalName()));
                    $item->article_id = $current_article_id;
                    $item->created_by = Auth::id();
                    try {
                        $item->save();
                        $result[] = $item;
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "article_doc",
                            "errors" => $e
                        ];
                    }
                }
            }

            $article_stage = new ArticleStage;
            $article_stage->article_id = $current_article_id;
            $article_stage->from_stage_id = $request->from_stage_id;
            $article_stage->to_stage_id = $request->to_stage_id;
            $article_stage->from_user_id = $request->from_user_id;
            $article_stage->to_user_id = $request->to_user_id; // waiting code
            $article_stage->plan_date = $request->plan_date;
            $article_stage->actual_date = $request->actual_date;
            $article_stage->article_stage_path = "/master_piece/public/articles_stage/";
            $article_stage->status = $request->status;
            $article_stage->remark = $request->remark;
            $article_stage->created_by = Auth::id();
            try {
                $article_stage->save();
                $current_stage_id = $article_stage->article_stage_id;
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article_stage",
                    "errors" => $e
                ];
            }

            foreach ($filesdata->file() as $key => $f) {
                $keye = explode('-', $key);
                if($keye[0]=='article_stage_doc') {
                    $result = array();
                    $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/articles_stage/' . $current_stage_id . '/';
                    $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                    $f->move($path,$filename);
                    $item = ArticleStageDoc::firstOrNew(array('article_stage_id' => $current_stage_id, 'doc_path' => 'articles_stage/' . $current_stage_id . '/' . $f->getClientOriginalName()));
                    $item->article_stage_id = $current_stage_id;
                    $item->created_by = Auth::id();
                    try {
                        $item->save();
                        $result[] = $item;
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "article_stage_doc",
                            "errors" => $e
                        ];
                    }
                }
            }

            foreach ($request->alerts as $a) { //waiting code
                $alert = new ArticleStageAlert;
                $alert->article_stage_id = $current_stage_id;
                $alert->user_id = $a->user_id;
                $alert->created_by = Auth::id();
                try {
                    $alert->save();
                } catch (Exception $e) {
                    $errors[] = [
                        "table_name" => "article_stage_alert",
                        "errors" => $e
                    ];
                }
            }

        } else {
            //update
            $article = Article::find($request->article_id);
            $article->stage_id = $request->to_stage_id;
            $article->article_name = $request->article_name;
            $article->article_type_id = $request->article_type_id;
            $article->procedure_id = $request->procedure_id;
            $article->doctor_id = $request->doctor_id;
            $article->writer = $request->from_user_id;
            $article->writing_start_date = $request->writing_start_date;
            $article->writing_end_date = $request->writing_end_date;
            $article->plan_date = $request->plan_date;
            $article->updated_by = Auth::id();
            try {
                $article->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article",
                    "errors" => $e
                ];
            }

            foreach ($filesdata->file() as $key => $f) {
                $keye = explode('-', $key);
                if($keye[0]=='article_doc') {
                    $result = array();
                    $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/article/' . $request->article_id . '/';
                    $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                    $f->move($path,$filename);
                    $item = ArticleDoc::firstOrNew(array('article_id' => $request->article_id, 'doc_path' => 'article/' . $request->article_id . '/' . $f->getClientOriginalName()));
                    $item->article_id = $request->article_id;
                    $item->created_by = Auth::id();
                    try {
                        $item->save();
                        $result[] = $item;
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "article_doc",
                            "errors" => $e
                        ];
                    }
                }
            }

            //$article_stage = ArticleStage::where('article_id', '=', $request->article_id)->delete();
            $article_stage = new ArticleStage;
            $article_stage->article_id = $request->article_id;
            $article_stage->from_stage_id = $request->from_stage_id;
            $article_stage->to_stage_id = $request->to_stage_id;
            $article_stage->from_user_id = $request->from_user_id;
            $article_stage->to_user_id = $request->to_user_id; // waiting code
            $article_stage->plan_date = $request->plan_date;
            $article_stage->actual_date = $request->actual_date;
            $article_stage->status = $request->status;
            $article_stage->remark = $request->remark;
            $article_stage->created_by = Auth::id();
            try {
                $article_stage->save();
                $current_stage_id = $article_stage->article_stage_id;
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article_stage",
                    "errors" => $e
                ];
            }

            foreach ($filesdata->file() as $key => $f) {
                $keye = explode('-', $key);
                if($keye[0]=='article_stage_doc') {
                    $result = array();
                    $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/articles_stage/' . $current_stage_id . '/';
                    $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                    $f->move($path,$filename);
                    $item = ArticleStageDoc::firstOrNew(array('article_stage_id' => $current_stage_id, 'doc_path' => 'articles_stage/' . $current_stage_id . '/' . $f->getClientOriginalName()));
                    $item->article_stage_id = $current_stage_id;
                    $item->created_by = Auth::id();
                    try {
                        $item->save();
                        $result[] = $item;
                    } catch (Exception $e) {
                        $errors[] = [
                            "table_name" => "article_stage_doc",
                            "errors" => $e
                        ];
                    }
                }
            }

            foreach ($request->alerts as $a) { //waiting code
                $alert = new ArticleStageAlert;
                $alert->article_stage_id = $current_stage_id;
                $alert->user_id = $a->user_id;
                $alert->created_by = Auth::id();
                try {
                    $alert->save();
                } catch (Exception $e) {
                    $errors[] = [
                        "table_name" => "article_stage_alert",
                        "errors" => $e
                    ];
                }
            }
        }

        empty($errors) ? DB::commit() : DB::rollback();
        empty($errors) ? $status = 200 : $status = 400;
        return response()->json(['status' => $status, 'errors' => $errors]);
    }
    
    // public function upload_case(Request $request)
    // {
    //     $result = array();
    //     $path = $_SERVER['DOCUMENT_ROOT'] . '/master_api/public/cases/' . $request->article_id . '/';
    //     foreach ($request->file() as $f) {
    //         $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
    //         $f->move($path,$filename);
    //         $item = ArticleStageDoc::firstOrNew(array('article_stage_id' => $request->article_id_stage, 'doc_path' => 'cases/' . $request->article_id . '/' . $f->getClientOriginalName()));
    //         $item->article_stage_id = $request->article_stage_id;
    //         $item->created_by = Auth::id();
    //         $item->save();
    //         $result[] = $item;              
    //     }   
        
    //     return response()->json($result);
        
    // }
    
    public function upload_article(Request $request)
    {
        $result = array();
        $path = $_SERVER['DOCUMENT_ROOT'] . '/master_piece/public/articles/' . $request->article_id . '/';
        foreach ($request->file() as $f) {
            $filename = iconv('UTF-8','windows-874',$f->getClientOriginalName());
            $f->move($path,$filename);
            $item = ArticleStageDoc::firstOrNew(array('article_stage_id' => $request->article_id_stage, 'doc_path' => 'articles/' . $request->article_id . '/' . $f->getClientOriginalName()));
            $item->article_stage_id = $request->article_stage_id;
            $item->created_by = Auth::id();
            $item->save();
            $result[] = $item;              
        }
        
        return response()->json($result);
        
    }

    public function import($id, Request $request) {

        if(empty($request->file())) {
            return response()->json(['status'=>400]);
        }

        $path_folder = base_path() . '/public/uploads/writer/'.Auth::id().'/'.$id.'';
        if (!File::exists($path_folder)) {
            //$this->makeDirectory($path_folder, $mode = 0777, true, true);
            $this->makeDirectory($path_folder, 0777, true, true); // create folder
        }
        File::deleteDirectory($path_folder);

        foreach($request->file() as $f) {
            $fileName = date('YmdHis') . '-' . $f->getClientOriginalName();
            $f->move($path_folder, $fileName);
        }
        return response()->json(['status'=>200]);
    }

    public function makeDirectory($path, $mode, $recursive, $force) {

        if ($force) {
            return @mkdir($path, $mode, $recursive);
        } else {
            return mkdir($path, $mode, $recursive);
        }
    }

    public function download_article_stage_doc($id) {
        //find path
        $item = DB::select("
            SELECT article__stage_path from article_stage where article_stage_id = '{$id}'
            ");

        $files = DB::select("
            SELECT doc.doc_path
            from article_stage_doc doc
            inner join article_stage stage
            on doc.article_stage_id = stage.article_stage_id
            where stage.article_stage_id = '{$id}'
        ");

        $public_dir = $_SERVER['DOCUMENT_ROOT'] . $item[0]['article_path'];
        $zipFileName = "".date('YmdHis').".zip";
        $zip = new ZipArchive;
        if ($zip->open($public_dir . '/' . $zipFileName, ZipArchive::CREATE) === TRUE) {    
            foreach ($files as $file) {
                $zip->addFile($public_dir , $file->doc_path);
            }
            $zip->close();
        }

        //$public_dir = public_path().'/uploads';
        // $public_file = public_path().'/uploads/test.pdf';
        // $zipFileName = "".date('YmdHis').".zip";
        // $zip = new ZipArchive;
        // if ($zip->open($public_dir . '/' . $zipFileName, ZipArchive::CREATE) === TRUE) {    
        //     $zip->addFile($public_file,'test.pdf');        
        //     $zip->close();
        //     // foreach ($files as $file) {
        //     //     $zip->addFile($public_dir , $file->doc_path);
        //     // }
        //     // $zip->close();
        // }

        $headers = array(
            'Content-Type' => 'application/octet-stream',
        );

        $filetopath = $public_dir.'/'.$zipFileName;

        if(file_exists($filetopath)) {
            return response()->download($filetopath,$zipFileName,$headers);
        }

        return response()->json(['status'=>200, 'errors' => 'file does not exist']);
    }

    public function download_article_doc($article_id) {
        //find path
        $item = DB::select("
            SELECT article_path from article where article_id = '{$article_id}'
            ");

        $files = DB::select("
            SELECT doc.article_path
            from article_doc doc
            inner join article ati
            on doc.article_id = ati.article_id
            where ati.article_id = '{$article_id}'
        ");

        $public_dir = $_SERVER['DOCUMENT_ROOT'] . $item[0]['article_path'];
        $zipFileName = "".date('YmdHis').".zip";
        $zip = new ZipArchive;
        if ($zip->open($public_dir . '/' . $zipFileName, ZipArchive::CREATE) === TRUE) {    
            foreach ($files as $file) {
                $zip->addFile($public_dir , $file->article_path);
            }
            $zip->close();
        }

        $headers = array(
            'Content-Type' => 'application/octet-stream',
        );

        $filetopath = $public_dir.'/'.$zipFileName;

        if(file_exists($filetopath)) {
            return response()->download($filetopath,$zipFileName,$headers);
        }

        return response()->json(['status'=>200, 'errors' => 'file does not exist']);
    }

    public function show(Request $request)
    {
        $article = DB::select("
            select a.article_id, a.article_name, a_t.article_type_id, a_t.article_type_name, m.procedure_id, m.procedure_name,
            d.doctor_id, d.doctor_name, u.screenName writer_name, u.userId writer_id, a.writing_start_date, a.writing_end_date,
            a.plan_date, a.article_path, a.status, 
            s.article_stage_id, s.from_stage_id, fs.stage_name from_stage_name, s.to_stage_id, ts.stage_name to_stage_name,
            s.to_user_id, tu.screenName to_user_name, s.plan_date workflow_plan_date, s.actual_date workflow_actual_date, s.remark
            from article a
            left outer join article_type a_t
            on a.article_type_id = a_t.article_type_id
            left outer join medical_procedure m 
            on a.procedure_id = m.procedure_id
            left outer join doctor d
            on a.doctor_id = d.doctor_id
            left outer join lportal.user_ u
            on a.writer = u.userId
            left outer join article_stage s
            on a.article_stage_id = s.article_stage_id
            left outer join stage fs
            on s.from_stage_id = fs.stage_id
            left outer join stage ts
            on s.to_stage_id = ts.stage_id
            left outer join lportal.user_ tu
            on s.to_user_id = tu.userId     
            where a.article_id = ?
        ", array($request->article_id));
        
        foreach ($article as $a) {
            $alerts = DB::select("
                select u.userId, u.screenName, u.emailAddress
                from article_stage_alert a
                left outer join lportal.user_ u
                on a.user_id = u.userId
                where a.article_stage_id = ?        
            ", array($a->article_stage_id));
            $a->alerts = $alerts;
        }

        $article_history = $this->article_stage_history($request->article_id);
        
        return response()->json(['article' => $article, 'article_history' => $article_history]);
    }

    public function article_stage_history($article_id)
    {
        $items = DB::select("
            SELECT a.article_stage_id, fs.stage_name from_stage_name, fu.screenName from_user_name, ts.stage_name to_stage_name, tu.screenName to_user_name, a.created_dttm, a.remark
            FROM article_stage a
            left outer join stage fs
            on a.from_stage_id = fs.stage_id
            left outer join stage ts
            on a.to_stage_id = ts.stage_id
            left outer join lportal.user_ fu
            on a.from_user_id = fu.userId
            left outer join lportal.user_ tu
            on a.to_user_id = tu.userId     
            where article_id = ?
            order by created_dttm asc
        ", array($article_id));
        
        foreach ($items as $i) {
            $alerts = DB::select("
                select a.user_id, u.screenName, u.emailAddress
                from article_stage_alert a
                inner join lportal.user_ u
                on a.user_id = u.userId
                where a.article_stage_id = ?
            ", array($i->article_stage_id));
            
            $docs = DB::select("
                select a.doc_path
                from article_stage_doc a
                where a.article_stage_id = ?
            ", array($i->article_stage_id));
            
            $i->alerts = $alerts;
            $i->docs = $docs;
            
        }
        
        return response()->json($items);
    }
}