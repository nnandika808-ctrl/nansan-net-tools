<?php
namespace App\Console\Commands;
use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Cache,Redis};
use Illuminate\Support\Str;
class Doctor extends Command
{
    protected $signature='nansan:doctor {--queue : Dispatch an actual worker probe}';
    protected $description='Print nonsecret database/cache/worker/scheduler evidence';
    public function handle(): int
    {
        $checks=['environment'=>app()->environment(),'database'=>'unavailable','redis'=>'unavailable','cache'=>'unavailable','queue'=>'not_tested','scheduler'=>'unavailable'];
        try{DB::select('select 1');$checks['database']=DB::connection()->getDriverName();}catch(\Throwable){}
        try{Redis::connection()->ping();$checks['redis']='ok';}catch(\Throwable){}
        $key='doctor.'.Str::uuid();
        try{Cache::put($key,'probe',30);$checks['cache']=Cache::get($key)==='probe'?'ok':'failed';Cache::forget($key);}catch(\Throwable){}
        try{
            if($this->option('queue')){QueueHeartbeat::dispatch($key);$until=microtime(true)+15;do{if(Cache::get($key)){$checks['queue']='worker_processed_unique_probe';break;}usleep(200000);}while(microtime(true)<$until);Cache::forget($key);if($checks['queue']==='not_tested')$checks['queue']='unavailable';}
            $stamp=Cache::get('scheduler.heartbeat');$checks['scheduler']=$stamp&&now()->diffInSeconds(\Carbon\Carbon::parse($stamp),true)<180?'fresh_heartbeat':'unavailable';
        }catch(\Throwable){}
        $this->line(json_encode($checks,JSON_THROW_ON_ERROR));
        return in_array('unavailable',$checks,true)||in_array('failed',$checks,true)?self::FAILURE:self::SUCCESS;
    }
}
