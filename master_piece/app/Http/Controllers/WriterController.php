<?php

namespace App\Http\Controllers;

use App\Article;
use App\ArticleDoc;
use App\CaseStage;
use App\ArticleStage;
use App\ArticleStageDoc;
use App\ArticleStageAlert;
use App\SocialMedia;
use App\CaseSocialMedia;
use App\ArticleSocialMedia;

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
use Mail;

class WriterController extends Controller {
    
    public function __construct() {
        $this->middleware('jwt.auth', ['except' => ['download_article_doc','download_article_stage_doc']]);
    }

    public function list_writer(Request $request) {
        $items = DB::select("
            SELECT a.writer,u.screenName
            from article a
            inner join lportal.user_ u
            on a.writer = u.userId
            where u.screenName
            like '%{$request->writer}%'
            group by a.writer
        ");
        return response()->json($items);
    }

    public function list_medical_procedure(Request $request) {
        $items = DB::select("
            SELECT m.procedure_id, m.procedure_name
            from medical_procedure m
            inner join article a on m.procedure_id = a.procedure_id
            group by m.procedure_id
        ");
        return response()->json($items);
    }

    public function list_doctor_article(Request $request) {
        $items = DB::select("
            SELECT d.doctor_id, d.doctor_name
            from doctor d
            inner join article a
            on d.doctor_id = a.doctor_id
            where d.doctor_name like '%{$request->doctor_name}%'
            group by d.doctor_id
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

    public function list_user_alert() {
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

    public function list_to_user(Request $request) {
        $items = DB::select("
            SELECT s.userId, '-', s.screenName
            FROM lportal.user_ s, lportal.users_roles ur, lportal.role_ r 
            where s.userId = ur.userId 
            and ur.roleId = r.roleId 
            and r.roleId in (22301,22302,22303,22304,22305,22306,22307,22308,22309,22310,22311,22312,22313)
            and s.screenName like '%{$request->screenName}%'
            group by s.userId
            order by s.screenName ASC
        ");
        return response()->json($items);
    }

    // public function send_to(Request $request) {
    //     $items = DB::select("
    //        SELECT s.userId, s.screenName 
    //         FROM lportal.user_ s, lportal.users_roles ur, lportal.role_ r 
    //         where s.userId = ur.userId
    //         and ur.roleId = r.roleId 
    //         and r.name in ('CEO','RH Manager','Director','Planner','RH Co','RH Admin','RH Writer','Production','Marketing Manager','Webmaster','Marcom','Social') 
    //         and s.screenName like '%{$request->screenName}%'
    //     ");
    //     return response()->json($items);
    // }

    public function current_action(Request $request)
    {
        if(empty($request->stage_id)) {
            $actions = DB::select("
            SELECT stage_id, stage_name, role_id
            from stage
            where stage_id = 201
            order by stage_id
        "); 

        } else {
            $actions = DB::select("
            SELECT stage_id, stage_name, role_id
            from stage
            where stage_id = {$request->stage_id}
            order by stage_id
        "); 
        }
        
        return response()->json($actions);      
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

        // $status = DB::select("
        //     SELECT status
        //     from workflow_stage
        //     where from_stage_id = ?
        //     ",array($workflow_stage[0]->to_stage_id));
        
        $actions = DB::select("
            SELECT s.stage_id, s.stage_name, s.stage_name as status
            from stage s
            -- left join lportal.user_ u
            -- on s.user_id = u.userId
            where s.stage_id in ({$workflow_stage[0]->to_stage_id})
            order by s.stage_id
        ");

        // $actions = DB::select(" 
        //     SELECT s.stage_id, s.stage_name, '{$workflow_stage[0]->status}' status, CONCAT(u.userId,'-',u.screenName) to_user
        //     from stage s
        //     left join lportal.role_ r
        //     on r.roleId = s.role_id
        //     inner join lportal.users_roles us
        //     on us.roleId = r.roleId
        //     inner join lportal.user_ u
        //     on u.userId = us.userId
        //     where s.stage_id in ({$workflow_stage[0]->to_stage_id})
        //     order by s.stage_id
        // ");
        
        return response()->json($actions);      
    }

    public function send_to_stage(Request $request)
    {
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

    // public function action_by(Request $request)
    // {
    //     $workflow_stage = DB::select("
    //         select from_stage_id
    //         from workflow_stage
    //         where from_stage_id = ?
    //     ",array($request->stage_id));
        
    //     if (empty($workflow_stage)) {
    //         return response()->json([]);
    //     }
        
    //     $actions = DB::select(" 
    //         select stage_id, stage_name
    //         from stage
    //         where stage_id in ({$workflow_stage[0]->from_stage_id})
    //         order by stage_id
    //     "); 
        
    //     return response()->json($actions);      
    // }

    public function search_writer(Request $request) {
        $writer = (empty($request->writer)) ? "like '%%'" : "= '{$request->writer}'";
        $procedure_id = (empty($request->procedure_id)) ? "like '%%'" : "= '{$request->procedure_id}'";
        $doctor_id = (empty($request->doctor_id)) ? "" : "and a.doctor_id = '{$request->doctor_id}'";
        $article_code = (empty($request->article_code)) ? "like '%%'" : "like '%{$request->article_code}%'";

        if(empty($request->writing_start_date) && empty($request->writing_end_date)) {
            $between = "and 1=1";
        } else if(empty($request->writing_start_date)) {
            $between = "and a.writing_start_date between '' and '{$request->writing_end_date}'";
        } else if(empty($request->writing_end_date)) {
            $between = "and a.writing_start_date >= '{$request->writing_start_date}'";
        } else {
            $between = "and a.writing_start_date between '{$request->writing_start_date}' and '{$request->writing_end_date}'";
        }

        $query = "
            SELECT a.article_id,
                    a.article_code,
                    a.article_name,
                    CONCAT(a.writer, '-', u.screenName) as writer,
                    a.writing_start_date,
                    a.writing_end_date,
                    a.plan_date,
                    at.article_type_name,
                    s.stage_name as status,
                    p.procedure_name,
                    d.doctor_name
            from article a
            inner join lportal.user_ u on a.writer = u.userId
            inner join article_type at on at.article_type_id = a.article_type_id
            inner join medical_procedure p on p.procedure_id = a.procedure_id
            left join doctor d on d.doctor_id = a.doctor_id
            inner join article_stage ats on ats.article_stage_id = a.article_stage_id
            inner join stage s on s.stage_id = ats.to_stage_id
            where a.writer $writer
            and a.procedure_id $procedure_id
            ".$doctor_id."
            and a.article_code $article_code
            ".$between."
        ";

        $items = DB::select($query);
        
        // Get the current page from the url if it's not set default to 1
        empty($request->page) ? $page = 1 : $page = $request->page;
        
        // Number of items per page
        empty($request->rpp) ? $perPage = 10 : $perPage = $request->rpp;
        
        $offSet = ($page * $perPage) - $perPage; // Start displaying items from this number

        // Get only the items you need using array_slice (only get 10 items since that's what you need)
        $itemsForCurrentPage = array_slice($items, $offSet, $perPage, false);
        
        // Return the paginator with only 10 items but with the count of all items and set the it on the correct page
        $result = new LengthAwarePaginator($itemsForCurrentPage, count($items), $perPage, $page);

        return response()->json($result);
    }

    public function cu(Request $filesdata) {
        //return response()->json($filesdata->all());
        // foreach ($filesdata->file() as $key => $value) {
        //     $keye = explode('-', $key);
        //     if($keye[0]=='article_doc') {
        //         return response()->json($keye[0]);
        //     }
        // }
        // return response()->json($filesdata->file());

        $request = (array)json_decode($filesdata->formdata);

        //return response()->json($request);

        $errors = [];
        $errors_validator = [];
        DB::beginTransaction();

        $validator_article = Validator::make($request, [
                'to_stage_id' => 'required|integer',
                'article_name' => 'required|max:256',
                'article_type_id' => 'required|integer',
                'procedure_id' => 'required|integer',
                //'doctor_id' => 'required|integer',
                'doctor_id' => 'integer',
                'from_user_id' => 'required|integer',
                'to_user_id' => 'required|integer',
                'writing_start_date' => 'required',
                'plan_date' => 'required',
            ],
            [
                'to_stage_id.required' => 'กรุณาเลือก ไปขั้นตอน.',
                'to_stage_id.integer'  => 'ไม่พบข้อมูล ไปขั้นตอน.',
                'article_name.required' => 'กรุณากรอก ชื่อบทความ.',
                'article_name.max'  => 'ชื่อบทความ ยาวเกินกำหนด.',
                'article_type_id.required' => 'กรุณาเลือก ประเภทบทความ.',
                'article_type_id.integer'  => 'ไม่พบข้อมูล ประเภทบทความ.',
                'procedure_id.required' => 'กรุณาเลือก หัตถการ.',
                'procedure_id.integer'  => 'ไม่พบข้อมูล หัตถการ.',
                //'doctor_id.required' => 'กรุณาเลือก แพทย์.',
                'doctor_id.integer'  => 'ไม่พบข้อมูล แพทย์.',
                'from_user_id.required' => 'กรุณากรอก ผู้เขียน.',
                'from_user_id.integer'  => 'ไม่พบข้อมูล ผู้เขียน.',
                'to_user_id.required' => 'กรุณาเลือก ส่งถึง.',
                'to_user_id.integer'  => 'ไม่พบข้อมูล ส่งถึง.',
                'writing_start_date.required' => 'กรุณาเลือก วันที่เริ่มเขียน.',
                'plan_date.required' => 'กรุณาเลือก กำหนดส่ง.',
            ]
        );
        if($validator_article->fails()){$errors_validator[] = $validator_article->errors();}

        if(!empty($request['social_media'])) {
            foreach($request['social_media'] as $social) {
                // return response()->json($social);
                $validator_social_media = Validator::make([
                    'social_media_id'           => $social->social_media_id,
                    'link'                 => $social->link,
                ], [
                    'social_media_id'           => 'required',
                    'link'                 => 'required|max:255',
                ],[ 'social_media_id.required'  => 'ช่องทางลงสื่อ : กรุณาเลือก ประเภทสื่อ.',
                    'link.required'        => 'ช่องทางลงสื่อ : กรุณากรอก ลิ้งค์.',
                ]);
                // return response()->json($validator_social_media);
            }
            if($validator_social_media->fails()){$errors_validator[] = $validator_social_media->errors();}
        }

        if(!empty($errors_validator)) {
            return response()->json(['status' => 400, 'errors' => $errors_validator]);
        }
        // return response()->json($request['social_media']);

        if(empty($request['article_id'])) {
            //add
            $article_id = DB::select("select article_id from article order by article_id desc limit 0,1");
            $article_code = empty($article_id[0]->article_id) ? 1 : $article_id[0]->article_id+1;

            $article = new Article;
            $article->article_code = "".$article_code."".$request['from_user_id']."";
            $article->article_name = $request['article_name'];
            $article->article_type_id = $request['article_type_id'];
            $article->procedure_id = $request['procedure_id'];
            $article->doctor_id = empty($request['doctor_id']) ? null : $request['doctor_id'];
            $article->writer = $request['from_user_id'];
            $article->writing_start_date = $request['writing_start_date'];
            $article->writing_end_date = $request['writing_end_date'];
            $article->plan_date = $request['plan_date'];
            $article->article_path = "/public/uploads/article/article_doc/";
            $article->status = $request['status'];
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

            if(!empty($filesdata->file())) {

                $path_folder = base_path() . '/public/uploads/article/article_doc/' . $current_article_id . '';
                if (!File::exists($path_folder)) {
                    $this->makeDirectory($path_folder, 0777, true, true); // create folder
                }
                foreach ($filesdata->file() as $key => $f) {
                    $keye = explode('-', $key);
                    if($keye[0]=='article_doc') {
                        $fileName = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                        $f->move($path_folder, $fileName);
                        $article_doc = new ArticleDoc;
                        $article_doc->article_path = $f->getClientOriginalName();
                        $article_doc->article_id = $current_article_id;
                        $article_doc->created_by = Auth::id();
                        try {
                            $article_doc->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "article_doc",
                                "errors" => $e
                            ];
                        }
                    }
                }
            }

            if(!empty($request['social_media'])) {
                foreach($request['social_media'] as $s) {
                    if (!$s->article_social_media_id) {
                        $article_social_media = new ArticleSocialMedia;
                        $article_social_media->article_id = $article->article_id;
                        $article_social_media->social_media_id = $s->social_media_id;
                        $article_social_media->link = $s->link;
                        $article_social_media->usr_name = $s->usr_name;
                        $article_social_media->pwd = $s->pwd;
                        $article_social_media->note = $s->note;
                        $article_social_media->created_by = Auth::id();
                        $article_social_media->updated_by = Auth::id();
                        try {
                        // return response()->json($article_social_media); 
                            $article_social_media->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "article_social_media",
                                "errors" => $e
                            ];
                        }
                    }
                }
            }

            if($request['to_stage_id']==206) {
                $stage_ID = $request['from_stage_id'];
            } else {
                $stage_ID = $request['to_stage_id'];
            }


            $article_stage = new ArticleStage;
            $article_stage->article_id = $current_article_id;
            $article_stage->from_stage_id = $request['from_stage_id'];
            $article_stage->to_stage_id = $stage_ID;
            $article_stage->from_user_id = $request['from_user_id'];
            $article_stage->to_user_id = $request['to_user_id'];
            $article_stage->plan_date = $request['plan_date'];
            $article_stage->actual_date = $request['actual_date'];
            $article_stage->article_stage_path = "/public/uploads/articles_stage/articles_stage_doc/";
            $article_stage->status = $request['status'];
            $article_stage->remark = $request['remark'];
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

            $update_article = Article::find($current_article_id);
            $update_article->article_stage_id = $current_stage_id;
            $update_article->updated_by = Auth::id();
            try {
                $update_article->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article",
                    "errors" => $e
                ];
            }

            if(!empty($filesdata->file())) {

                $path_folder = base_path() . '/public/uploads/articles_stage/articles_stage_doc/' . $current_stage_id . '';
                if (!File::exists($path_folder)) {
                    $this->makeDirectory($path_folder, 0777, true, true); // create folder
                }

                foreach ($filesdata->file() as $key => $f) {
                    $keye = explode('-', $key);
                    if($keye[0]=='article_stage_doc') {

                        $fileName = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                        $f->move($path_folder, $fileName);

                        $article_stage_doc = new ArticleStageDoc;
                        $article_stage_doc->doc_path = $f->getClientOriginalName();
                        $article_stage_doc->article_stage_id = $current_stage_id;
                        $article_stage_doc->created_by = Auth::id();
                        try {
                            $article_stage_doc->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "article_stage_doc",
                                "errors" => $e
                            ];
                        }
                    }
                }
            }

            // if(!empty($request['alert']->user_id)) {
            //     if(empty($errors)) {
            //         $to = array();
            //         try {
            //             $data = ["chief_emp_name" => "the boss", "emp_name" => "the bae", "status" => "excellent"];

            //             $from = 'gjtestmail2017@gmail.com';

            //             foreach ($request['alert']->user_id as $a) {
            //                 $input_mail = explode('|', $a);
            //             // return response()->json($input_mail);
            //                 $to[] = $input_mail[1];
            //                 $alert = new ArticleStageAlert;
            //                 $alert->article_stage_id = $current_stage_id;
            //                 $alert->user_id = $input_mail[0];
            //                 $alert->created_by = Auth::id();
            //                 try {
            //                     $alert->save();
            //                 } catch (Exception $e) {
            //                     $errors[] = [
            //                         "table_name" => "article_stage_alert",
            //                         "errors" => $e
            //                     ];
            //                 }
            //             }

            //             Mail::send('emails.status', $data, function($message) use ($from, $to) {
            //                 $message->from($from, 'Review Hunter');
            //                 $message->to($to)->subject('Alert!');
            //             });

            //         } catch (Exception $e) {
            //             $errors[] = $e->getMessage();
            //         }
            //     }
            // }

        } else {
            // return response()->json($request);
            //update
            $article = Article::find($request['article_id']);
            $article->article_name = $request['article_name'];
            $article->article_type_id = $request['article_type_id'];
            $article->procedure_id = $request['procedure_id'];
            $article->doctor_id = empty($request['doctor_id']) ? null : $request['doctor_id'];
            $article->writer = $request['from_user_id'];
            $article->writing_start_date = $request['writing_start_date'];
            $article->writing_end_date = $request['writing_end_date'];
            $article->plan_date = $request['plan_date'];
            $article->status = $request['status'];
            $article->updated_by = Auth::id();
            try {
                $article->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article",
                    "errors" => $e
                ];
            }

            if(!empty($filesdata->file())) {

                $path_folder = base_path() . '/public/uploads/article/article_doc/' . $request['article_id'] . '';
                if (!File::exists($path_folder)) {
                    $this->makeDirectory($path_folder, 0777, true, true); // create folder
                }

                foreach ($filesdata->file() as $key => $f) {
                    $keye = explode('-', $key);
                    if($keye[0]=='article_doc') {

                        $fileName = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                        $path_file = $path_folder . "/" . $fileName;

                        if(File::exists($path_file)) {
                            File::delete($path_file);
                        }

                        $f->move($path_folder, $fileName);

                        $article_doc = new ArticleDoc;
                        $article_doc->article_path = $f->getClientOriginalName();
                        $article_doc->article_id = $request['article_id'];
                        $article_doc->created_by = Auth::id();
                        try {
                            $article_doc->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "article_doc",
                                "errors" => $e
                            ];
                        }
                    }
                }
            }

            if(!empty($request['social_media'])) {
                foreach($request['social_media'] as $s) {
                    if ($s->article_social_media_id) {
                        $article_social_media                   = ArticleSocialMedia::findOrFail($s->article_social_media_id);
                        $article_social_media->article_id       = $article->article_id;
                        $article_social_media->social_media_id  = $s->social_media_id;
                        $article_social_media->link = $s->link;
                        $article_social_media->usr_name = $s->usr_name;
                        $article_social_media->pwd = $s->pwd;
                        $article_social_media->note = $s->note;
                        $article_social_media->updated_by = Auth::id();
                        
                    }else{
                        $article_social_media                   = new ArticleSocialMedia;
                        $article_social_media->article_id       = $article->article_id;
                        $article_social_media->social_media_id  = $s->social_media_id;
                        $article_social_media->link = $s->link;
                        $article_social_media->usr_name = $s->usr_name;
                        $article_social_media->pwd = $s->pwd;
                        $article_social_media->note = $s->note;
                        $article_social_media->created_by = Auth::id();
                        $article_social_media->updated_by = Auth::id();
                    }
                    try {
                            $article_social_media->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "article_social_media",
                                "errors" => $e
                            ];
                        }
                }
            }

            if($request['to_stage_id']==206) {
                $stage_ID = $request['from_stage_id'];
            } else {
                $stage_ID = $request['to_stage_id'];
            }

            $article_stage = new ArticleStage;
            $article_stage->article_id = $request['article_id'];
            $article_stage->from_stage_id = $request['from_stage_id'];
            $article_stage->to_stage_id = $stage_ID;
            $article_stage->from_user_id = $request['from_user_id'];
            $article_stage->to_user_id = $request['to_user_id'];
            $article_stage->plan_date = $request['plan_date'];
            $article_stage->actual_date = $request['actual_date'];
            $article_stage->article_stage_path = "/public/uploads/articles_stage/articles_stage_doc/";
            $article_stage->status = $request['status'];
            $article_stage->remark = $request['remark'];
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

            $update_article = Article::find($request['article_id']);
            $update_article->article_stage_id = $current_stage_id;
            $update_article->updated_by = Auth::id();
            try {
                $update_article->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article",
                    "errors" => $e
                ];
            }

            if(!empty($filesdata->file())) {

                $path_folder = base_path() . '/public/uploads/articles_stage/articles_stage_doc/' . $current_stage_id . '';
                if (!File::exists($path_folder)) {
                    $this->makeDirectory($path_folder, 0777, true, true); // create folder
                }

                foreach ($filesdata->file() as $key => $f) {
                    $keye = explode('-', $key);
                    if($keye[0]=='article_stage_doc') {

                        $fileName = iconv('UTF-8','windows-874',$f->getClientOriginalName());
                        $path_file = $path_folder . "/" . $fileName;

                        if(File::exists($path_file)) {
                            File::delete($path_file);
                        }

                        $f->move($path_folder, $fileName);

                        $articles_stage_doc = new ArticleStageDoc;
                        $articles_stage_doc->doc_path = $f->getClientOriginalName();
                        $articles_stage_doc->article_stage_id = $current_stage_id;
                        $articles_stage_doc->created_by = Auth::id();
                        try {
                            $articles_stage_doc->save();
                        } catch (Exception $e) {
                            $errors[] = [
                                "table_name" => "articles_stage_doc",
                                "errors" => $e
                            ];
                        }
                    }
                }
            }
        }

        if(!empty($request['alert']->user_id)) {
            foreach ($request['alert']->user_id as $a) {
                $input_mail = explode('|', $a);
                $alert = new ArticleStageAlert;
                $alert->article_stage_id = $current_stage_id;
                $alert->user_id = $input_mail[0];
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

        if(empty($errors)) {
            $article_id = (empty($current_article_id)) ? $request['article_id'] : $current_article_id;

            $email_body = DB::select("
                SELECT u.screenName as to_user_name,
                        ua.screenName as user_alert,
                        m.procedure_name,
                        stf.stage_name as from_stage_name,
                        stt.stage_name as to_stage_name,
                        a.status,
                        a.article_name,
                        a.article_code
                from article a
                inner join medical_procedure m
                on m.procedure_id = a.procedure_id
                inner join article_stage ast
                on ast.article_stage_id = a.article_stage_id
                inner join stage stf
                on stf.stage_id = ast.from_stage_id
                inner join stage stt
                on stt.stage_id = ast.to_stage_id
                inner join lportal.user_ u
                on u.userId = ast.to_user_id
                left join article_stage_alert asa
                on asa.article_stage_id = ast.article_stage_id
                left join lportal.user_ ua
                on ua.userId = asa.user_id
                where a.article_id = {$article_id}
            ");
        }

        if(!empty($request['alert']->user_id)) {
            if(empty($errors)) {
                $to = array();
                try {

                    $data = [
                        "to_user" => $email_body[0]->to_user_name, 
                        "article_code" => $email_body[0]->article_code, 
                        "procedure" => $email_body[0]->procedure_name,
                        "from_stage_name" => $email_body[0]->from_stage_name,
                        "to_stage_name" => $email_body[0]->to_stage_name,
                        "status" => $email_body[0]->status,
                        "user_alert" => $request['from_user_name']
                    ];

                    $from = 'gjtestmail2017@gmail.com';

                    foreach ($request['alert']->user_id as $a) {
                        $input_mail = explode('|', $a);
                        $to[] = $input_mail[1];
                    }

                    Mail::send('emails.writer', $data, function($message) use ($from, $to) {
                        $message->from($from, 'Review Hunter');
                        $message->to($to)->subject('แจ้งเตือน ระบบ Review Hunter');
                    });

                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if(!empty($request['to_user_email'])) {
            if(empty($errors)) {
                $to = array();
                try {
                    $data = [
                        "to_user" => $email_body[0]->to_user_name, 
                        "article_code" => $email_body[0]->article_code, 
                        "procedure" => $email_body[0]->procedure_name,
                        "from_stage_name" => $email_body[0]->from_stage_name,
                        "to_stage_name" => $email_body[0]->to_stage_name,
                        "status" => $email_body[0]->status,
                        "user_alert" => $request['from_user_name']
                    ];

                    $from = 'gjtestmail2017@gmail.com';

                    $to[] = $request['to_user_email'];

                    Mail::send('emails.writer', $data, function($message) use ($from, $to) {
                        $message->from($from, 'Review Hunter');
                        $message->to($to)->subject('แจ้งเตือน ระบบ Review Hunter');
                    });

                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

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

    public function del_rec(Request $req) {
        try {
            ArticleSocialMedia::findOrFail($req->id)->delete();
            return response()->json(['status' => 200]);
        }catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' =>  $e]);
        }
    }
    
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

        $path_folder = base_path() . '/public/uploads/article/article_doc/' . $id . '';

        foreach ($request->file() as $key => $f) {

            $fileName = iconv('UTF-8','windows-874',$f->getClientOriginalName());
            $path_file = $path_folder . "/" . $fileName;

            if(File::exists($path_file)) {
                File::delete($path_file);
            }

            $f->move($path_folder, $fileName);

            $article_doc = new ArticleDoc;
            $article_doc->article_path = $f->getClientOriginalName();
            $article_doc->article_id = $id;
            $article_doc->created_by = Auth::id();
            try {
                $article_doc->save();
            } catch (Exception $e) {
                $errors[] = [
                    "table_name" => "article_doc",
                    "errors" => $e
                ];
            }
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

        $item = DB::select("
            SELECT article_stage_path from article_stage where article_stage_id = '{$id}'
        ");

        $files = DB::select("
            SELECT doc.doc_path
            from article_stage_doc doc
            inner join article_stage ati
            on doc.article_stage_id = ati.article_stage_id
            where ati.article_stage_id = '{$id}'
        ");

        if(empty($files)) {
            return response()->json(['status'=>400, 'errors' => 'file does not exist']);
        }

        $base_path = base_path() . $item[0]->article_stage_path;
        $zipFileName = "Workflow-Document_".$id.".zip";
        $base_path_file = $base_path . $id ."/". $zipFileName;

        if(File::exists($base_path_file)) {
            File::delete($base_path_file);
        }

        $zip = new ZipArchive;
        if ($zip->open($base_path . $id ."/". $zipFileName, ZipArchive::CREATE) === TRUE) {    
            foreach ($files as $file) {
                $set_utf8_filename = iconv('UTF-8','windows-874',$file->doc_path);
                $zip->addFile($base_path . $id ."/". $set_utf8_filename, $set_utf8_filename);
            }
            $zip->close();
        }

        $headers = array(
            'Content-Type' => 'application/octet-stream',
        );

        $filetopath = $base_path . $id ."/". $zipFileName;

        if(file_exists($filetopath)) {
            return response()->download($filetopath,$zipFileName,$headers);
        }
    }

    public function download_article_doc($article_id) {



        // $public_dir = public_path().'/uploads';
        // $public_file = public_path().'/uploads/test.txt';
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

        // $headers = array(
        //     'Content-Type' => 'application/octet-stream',
        // );

        // $filetopath = $public_dir . $zipFileName;

        // if(file_exists($filetopath)) {
        //     return response()->download($filetopath,$zipFileName,$headers);
        // }


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

        if(empty($files)) {
            return response()->json(['status'=>400, 'errors' => 'file does not exist']);
        }

        // $fileName = iconv('UTF-8','windows-874',$files);
        // return response()->json($fileName);

        //$base_path = base_path() . $item[0]->article_path;
        //$zipFileName = "".date('YmdHis').".zip";

        $base_path = base_path() . $item[0]->article_path;
        $zipFileName = "Article-Document_".$article_id.".zip";
        $base_path_file = $base_path . $article_id ."/". $zipFileName;

        if(File::exists($base_path_file)) {
            File::delete($base_path_file);
        }

        //return response()->json($base_path . $article_id ."/". $zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($base_path . $article_id ."/". $zipFileName, ZipArchive::CREATE) === TRUE) {    
            foreach ($files as $file) {
                $set_utf8_filename = iconv('UTF-8','windows-874',$file->article_path);
                //return response()->json($base_path . $article_id ."/". iconv('UTF-8','windows-874',$file->article_path));
                $zip->addFile($base_path . $article_id ."/". $set_utf8_filename, $set_utf8_filename);
                //$zip->addFile($base_path . $article_id ."/". $file->article_path, $file->article_path);
            }
            $zip->close();
        }

        $headers = array(
            'Content-Type' => 'application/octet-stream',
        );

        $filetopath = $base_path . $article_id ."/". $zipFileName;

        if(file_exists($filetopath)) {
            return response()->download($filetopath,$zipFileName,$headers);
        }
        //return response()->json(['status'=>200, 'errors' => 'file does not exist']);
    }

    public function show(Request $request)
    {

        $article = DB::select("
            select a.article_id, a.article_code, a.article_name, CONCAT(a.article_path,a.article_id,'/',a_d.article_path) as doc_path, a_t.article_type_id, m.procedure_id, m.procedure_name,
            d.doctor_id, a_d.article_doc_id, a_d.article_path as doc_file, d.doctor_name, u.screenName writer_name, u.userId writer_id, a.writing_start_date, a.writing_end_date,
            a.plan_date, a.status, 
            s.article_stage_id, s.from_stage_id, fs.stage_name from_stage_name, s.to_stage_id, ts.stage_name to_stage_name,
            s.to_user_id, tu.screenName to_user_name, s.plan_date workflow_plan_date, s.actual_date workflow_actual_date, s.remark
            from article a
            left outer join article_doc a_d
            on a_d.article_id = a.article_id
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
        $social = ArticleSocialMedia::where('article_id',$request->article_id)->get();

        $article_history = $this->article_stage_history($request->article_id);
        
        return response()->json(['social' => $social,'article' => $article, 'article_history' => $article_history]);
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
        
        return $items;
    }

    public function check_disabled(Request $request) {
        $item = DB::select("
            SELECT ur.roleId, u.screenName
            FROM lportal.users_roles ur
            inner join lportal.user_ u
            on u.userId = ur.userId
            where ur.roleId = {$request->role_id}
            and u.screenName = '".Auth::id()."'
        ");

        if(empty($item)) {
            return response()->json(['status' => 400]);
        } else {
            return response()->json(['status' => 200]);
        }
    }

    public function list_article_code(Request $request) {
        $items = DB::select("
            SELECT article_code
            from article
            where article_code like '%{$request->article_code}%'
        ");
        return response()->json($items);
    }

    public function del_doc_file(Request $req) {
        try {
            $item = ArticleDoc::findOrFail($req->article_doc_id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'data' => 'ArticleDoc not found.']);
        }   

        try {
            $item->delete();
            $items = DB::select("
                select article_path as doc_file, article_doc_id from article_doc
                where article_id = {$req->article_id}
            ");
        } catch (Exception $e) {
            if ($e->errorInfo[1] == 1451) {
                return response()->json(['status' => 400, 'data' => 'ไม่สามารถลบได้ เนื่องจากมีการใช้งานอยู่']);
            } else {
                return response()->json($e->errorInfo);
            }
        }
        return response()->json(['status' => 200, 'article' => $items]);
    }

    // public function destroy_case_social_media(Request $req){
    //     try {
    //         $arr = json_decode($req->arr);
    //         foreach ($arr as $edu) {
    //             CaseSocialMedia::find($edu)->delete();
    //         } 
    //         return response()->json(['status' => 200, 'data' => $req->arr]);
    //     }catch (ModelNotFoundException $e) {
    //         return response()->json(['status' => 404, 'data' => 'Can\'t not Destory.']);
    //     }
    // }

    public function get_social_media(Request $request) {
        $data = SocialMedia::where('is_active',1)->get(['social_media_id','social_media_name']);
        return response()->json(['status'=>200,'articleSocialMedia'=>$data]);
    }
}