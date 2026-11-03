<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeat;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class Doctor extends Command
{
    protected $signature = 'nansan:doctor {--queue : Dispatch an actual worker probe}';

    protected $description = 'Print nonsecret database/cache/worker/scheduler evidence';

    public function handle(): int
    {
        $checks = ['environment' => app()->environment(), 'database' => 'unavailable', 'redis' => 'unavailable', 'cache' => 'unavailable', 'queue' => 'not_tested', 'scheduler' => 'unavailable'];
        try {
            DB::select('select 1');
            $checks['database'] = DB::connection()->getDriverName();
        } catch (\Throwable) {
        }
        try {
            Redis::connection()->ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable) {
        }
        $key = 'doctor.'.Str::uuid();
        try {
            Cache::put($key, 'probe', 30);
            $checks['cache'] = Cache::get($key) === 'probe' ? 'ok' : 'failed';
            Cache::forget($key);
        } catch (\Throwable) {
        }
        try {
            if ($this->option('queue')) {
                if (! in_array(config('queue.default'), ['redis', 'database'], true)) {
                    $checks['queue'] = 'unavailable';
                    $checks['queue_error'] = 'PersistentQueueRequired';
                    throw new \RuntimeException('Persistent queue required');
                }
                QueueHeartbeat::dispatch($key);
                $until = microtime(true) + 15;
                do {
                    if (Cache::get($key)) {
                        $checks['queue'] = 'worker_processed_unique_probe';
                        break;
                    }usleep(200000);
                } while (microtime(true) < $until);
                Cache::forget($key);
                if ($checks['queue'] === 'not_tested') {
                    $checks['queue'] = 'unavailable';
                }
            }
        } catch (\Throwable $error) {
            $checks['queue_error'] = class_basename($error);
        }
        try {
            $stamp = Cache::get('scheduler.heartbeat');
            $checks['scheduler'] = $stamp && now()->diffInSeconds(Carbon::parse($stamp), true) < 180 ? 'fresh_heartbeat' : 'unavailable';
        } catch (\Throwable $error) {
            $checks['scheduler_error'] = class_basename($error);
        }
        $this->line(json_encode($checks, JSON_THROW_ON_ERROR));

        return in_array('unavailable', $checks, true) || in_array('failed', $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
