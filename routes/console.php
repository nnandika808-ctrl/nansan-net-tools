<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn()=>\Illuminate\Support\Facades\Cache::put('scheduler.heartbeat',now()->toIso8601String(),600))->everyMinute();
Schedule::job(new \App\Jobs\QueueHeartbeat)->everyFiveMinutes()->withoutOverlapping();
Schedule::command('nansan:purge-accounts')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('nansan:backup')->dailyAt('01:00')->withoutOverlapping()->when(fn()=>(bool)config('nansan.backup_disk'));
Schedule::call(function () {
    $expired=\Illuminate\Support\Facades\DB::table('backup_records')->where('created_at','<',now()->subDays(config('nansan.backup_retention_days')))->get();
    foreach($expired as $backup)if($backup->path&&str_starts_with($backup->path,'nansan/')) {
        \Illuminate\Support\Facades\Storage::disk($backup->disk)->delete($backup->path);
        \Illuminate\Support\Facades\DB::table('backup_records')->where('id',$backup->id)->delete();
    }
})->dailyAt('04:00')->when(fn()=>(bool)config('nansan.backup_disk'));
Schedule::call(function () {
    \Illuminate\Support\Facades\DB::table('audit_logs')->where('created_at','<',now()->subDays(90))->delete();
    \Illuminate\Support\Facades\DB::table('user_activity_logs')->where('created_at','<',now()->subDays(30))->delete();
})->dailyAt('03:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
