<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Schedule::call(fn () => Cache::put('scheduler.heartbeat', now()->toIso8601String(), 600))->everyMinute();
Schedule::job(new QueueHeartbeat)->everyFiveMinutes()->withoutOverlapping();
Schedule::command('nansan:purge-accounts')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('nansan:backup')->dailyAt('01:00')->withoutOverlapping()->when(fn () => (bool) config('nansan.backup_disk'));
Schedule::call(function () {
    $expired = DB::table('backup_records')->where('created_at', '<', now()->subDays(config('nansan.backup_retention_days')))->get();
    foreach ($expired as $backup) {
        if ($backup->path && str_starts_with($backup->path, 'nansan/')) {
            Storage::disk($backup->disk)->delete($backup->path);
            DB::table('backup_records')->where('id', $backup->id)->delete();
        }
    }
})->dailyAt('04:00')->when(fn () => (bool) config('nansan.backup_disk'));
Schedule::call(function () {
    DB::table('page_view_daily')->where('day', '<', now()->subDays(90)->toDateString())->delete();
    DB::table('audit_logs')->where('created_at', '<', now()->subDays(90))->delete();
    DB::table('user_activity_logs')->where('created_at', '<', now()->subDays(30))->delete();
})->dailyAt('03:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
