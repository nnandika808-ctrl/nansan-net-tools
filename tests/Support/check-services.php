<?php
if (!getenv('CI')) throw new RuntimeException('Service integration check is restricted to the isolated CI environment.');
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\DB::select('select 1');
Illuminate\Support\Facades\Redis::connection()->ping();
App\Jobs\QueueHeartbeat::dispatch();
Illuminate\Support\Facades\Artisan::call('queue:work',['--once'=>true,'--stop-when-empty'=>true]);
if (!Illuminate\Support\Facades\Cache::get('queue.heartbeat')) throw new RuntimeException('Queue heartbeat missing.');
echo "MySQL, Redis and queued heartbeat integration passed.\n";
