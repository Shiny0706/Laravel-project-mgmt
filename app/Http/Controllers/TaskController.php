<?php
namespace App\Http\Controllers;

use Session;
use Illuminate\Http\Request;

// import our models
use App\Project;
use App\Task;
use App\TaskFiles;
use App\User; 

use Illuminate\Support\Facades\Input; 

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // dd() ;
        // $tasks = Task::all() ;  // retrieve all Tasks
        $users =  User::all() ; 
        $tasks  = Task::orderBy('created_at', 'desc')->paginate(10) ;  // Paginate Tasks 
        return view('task.tasks')->with('tasks', $tasks) 
                                 ->with('users', $users ) ;
    }

    public function tasklist( $projectid ) {

        // dd($projectid);
        $users =  User::all() ;
        $p_name = Project::find($projectid) ;
        $task_list = Task::where('project_id','=' , $projectid)->get();
        return view('task.list')->with('users', $users) 
                                ->with('p_name', $p_name)
                                ->with('task_list', $task_list) ;
    }


    public function view($id)  {
        $images_set = [] ;
        $files_set = [] ;
        $images_array = ['png','gif','jpeg','jpg'] ;
        // get task file names with task_id number
        $taskfiles = TaskFiles::where('task_id', $id )->get() ;

        if ( count($taskfiles) > 0 ) { 
            foreach ( $taskfiles as $taskfile ) {
                // explode the filename into 2 parts: the filename and the extension
                $taskfile = explode(".", $taskfile->filename ) ;
                // store images only in one array
                // $taskfile[0] = filename
                // $taskfile[1] = jpg
                if ( in_array($taskfile[1], $images_array ) ) 
                    $images_set[] = $taskfile[0] . '.' . $taskfile[1] ;
                    // the rest non image files are stored here
                else
                    $files_set[] = $taskfile[0] . '.' . $taskfile[1]; 
            }
        }

        $task_view = Task::find($id) ;

        // Get task created and due dates
        $from = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $task_view->created_at);
        $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $task_view->duedate ); // add here the due date from create task

        $current_date = \Carbon\Carbon::now();

        // Format dates for Humans
        $formatted_from = $from->toRfc850String();  
        $formatted_to   = $to->toRfc850String();

        // Get Difference between current_date and duedate = days left to complete task
        // $diff_in_days = $from->diffInDays($to);
        $diff_in_days = $current_date->diffInDays($to);

        // Check for overdue tasks
        $is_overdue = ($from->gt($to) ) ? true : false ;

        // $task_view->project->project_name   will output the project name for this specific task
        // to populate the right sidebar with related tasks
        $projects = Project::all() ;
        return view('task.view')
            ->with('task_view', $task_view) 
            ->with('projects', $projects) 
            ->with('taskfiles', $taskfiles)
            ->with('diff_in_days', $diff_in_days )
            ->with('is_overdue', $is_overdue) 
            ->with('formatted_from', $formatted_from ) 
            ->with('formatted_to', $formatted_to )
            ->with('images_set', $images_set)
            ->with('files_set', $files_set) ;
    }


    public function sort( $key ) {
        $users = User::all() ;
        // dd ($key) ; 
        switch($key) {
            case 'task':
                $tasks = Task::orderBy('task')->paginate(10); // replace get() with paginate()
            break;
            case 'priority':
                $tasks = Task::orderBy('priority')->paginate(10);
            break;
            case 'completed':
                $tasks = Task::orderBy('completed')->paginate(10);
            break;
        }

        return view('task.tasks')->with('users', $users)
                                ->with('tasks', $tasks) ;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $projects = Project::all()  ;
        $users = User::all() ;
        return view('task.create')->with('projects', $projects) 
                                  ->with('users', $users) ;        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all() ) ;
        $tasks_count = Task::count() ;
        
        if ( $tasks_count < 20  ) { 
            // dd( $request->all()  ) ;
            // dd($request->file('photos'));

            $this->validate( $request, [
                'task_title' => 'required',
                'task'       => 'required',
                'project_id' => 'required|numeric',
                'photos.*'   => 'sometimes|required|mimes:png,gif,jpeg,jpg,txt,pdf,doc',  // photos is an array: photos.*
                'duedate'    => 'required'
            ]) ;

            // dd($request->all() ) ;
            // First save Task Info
            $task = Task::create([
                'project_id' => $request->project_id,
                'user_id'    => $request->user,
                'task_title' => $request->task_title,
                'task'       => $request->task,
                'priority'   => $request->priority,
                'duedate'    => $request->duedate
            ]);

            // Then save files using the newly created ID above
            if ( $request->file('photos' ) != ''  ) { 
                foreach ($request->photos as $file) {
                    // To Storage
                    //$filename = $file->store('public'); // /storage/app/public

                    // filename will be saved as: public/wKZsF9ltDSNj82ynh.png
                    // explode this value at / and get the second element
                    // $filename = explode("/", $filename ) ; // FOR STORAGE

                    // If you want to save into  /public/images
                    $filename = $file->getClientOriginalName();  // get original file name ex:   cat.jpg
                    $file->move('images',$filename);

                    // save to DB
                    TaskFiles::create([
                        'task_id'  => $task->id, // newly created ID
                        'filename' => $filename  // For Regular Public Images
                        //'filename' => $filename[1]  // [0] => public, [1] => wKZsF9ltDSNj82ynh.png FOR STORAGE
                    ]);
                }
            }
    
            Session::flash('success', 'Task Created') ;
            return redirect()->route('task.show') ; 
        }
        
        else {
            Session::flash('info', 'Please delete some tasks, Demo max tasks: 20') ;
            return redirect()->route('task.show') ;         
        }

    }


    public function completed($id)
    {
        $task_complete = Task::find($id) ;
        $task_complete->completed = 1;
        $task_complete->save() ;
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
// $project::find(1)->tasks; retrieves the project record with id 1 and lists all tasks that have the project_id 1.

        //$task_edit = Task::find($id)->projects ; // NULL
        $task = Task::find($id)  ; 
        // dd($task) ;
        $projects = Project::all() ;
        $users = User::all() ;
        //$project_edit = Project::find($id)->tasks ; 
        // echo '<pre>';
        // print_r( Project::all() );
        // echo '</pre>';

        // dd($task_edit) ;  // returns NULL

        //dd($task_edit) ;  // Works
        //$project_edit = Project::find($id) ;
        return view('task.edit')->with('task', $task)
                                ->with('projects', $projects ) 
                                ->with('users', $users);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // dd( $request->all() ) ;
        $update_task = Task::find($id) ;
        // dd( $update_task->id ) ;

        $this->validate( $request, [
            'task_title' => 'required',
            'task'       => 'required',
            'project_id' => 'required|numeric',
            'photos.*'   => 'sometimes|required|mimes:png,gif,jpeg,jpg,txt,pdf,doc' // photos is an array: photos.*
        ]) ;

        $update_task->task_title = $request->task_title; 
        $update_task->task       = $request->task;
        $update_task->user_id    = $request->user_id;
        $update_task->project_id = $request->project_id;
        $update_task->priority   = $request->priority;
        $update_task->completed  = $request->completed;
        $update_task->duedate    = $request->duedate;

        if ( $request->file('photos' ) != ''  ) {
            foreach ($request->photos as $file) {
                // If you want to save into  /public/images
                $filename = $file->getClientOriginalName();  // get original file name ex:   cat.jpg
                $file->move('images',$filename);

                // save to DB
                TaskFiles::create([
                    'task_id'  => $request->task_id,
                    'filename' => $filename  // For Regular Public Images
                ]);
            }        
        }


        $update_task->save() ;
        
        Session::flash('success', 'Task was sucessfully edited') ;
        return redirect()->route('task.show') ;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $delete_task = Task::find($id) ;
        $delete_task->delete() ;
        Session::flash('success', 'Task was deleted') ;
        return redirect()->back();
    }

    public function searchTask() {
        $value = Input::get('search_task');
        $tasks = Task::where('task', 'LIKE', '%' . $value . '%')->limit(25)->get();

        return view('task.search', compact('value', 'tasks')  ) ;
    }


}
