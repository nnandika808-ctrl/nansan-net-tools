<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\{DB,Cache,Redis};
use Inertia\Inertia;
class MonitoringController extends Controller
{
    public function index()
    {
        $checks=[];
        try { DB::select('select 1'); $checks['database']='ok'; } catch(\Throwable) { $checks['database']='unavailable'; }
        try { Redis::connection()->ping(); $checks['redis']='ok'; } catch(\Throwable) { $checks['redis']='unavailable'; }
        $checks['scheduler']=Cache::get('scheduler.heartbeat'); $checks['queue']=Cache::get('queue.heartbeat');
        $checks['load']=function_exists('sys_getloadavg')?sys_getloadavg():null;
        $checks['disk_free_bytes']=@disk_free_space(storage_path())?:null;
        $checks['disk_total_bytes']=@disk_total_space(storage_path())?:null;
        $checks['ram']=is_readable('/proc/meminfo')?$this->memory():null;
        $checks['tunnel']='unavailable';
        $checks['backup_configuration']=config('nansan.backup_disk')&&config('nansan.backup_key')?'configured':'unavailable';
        return Inertia::render('Admin',['section'=>'monitoring','checks'=>$checks,'records'=>DB::table('backup_records')->latest('id')->limit(15)->get()]);
    }
    private function memory(): array
    {
        $text=file_get_contents('/proc/meminfo'); preg_match('/MemTotal:\s+(\d+)/',$text,$total); preg_match('/MemAvailable:\s+(\d+)/',$text,$available);
        return ['total_bytes'=>isset($total[1])?(int)$total[1]*1024:null,'available_bytes'=>isset($available[1])?(int)$available[1]*1024:null];
    }
}
