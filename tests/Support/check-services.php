<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

if (! getenv('CI')) {
    throw new RuntimeException('Service integration check is restricted to the isolated CI environment.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DB::select('select 1');
Redis::connection()->ping();
QueueHeartbeat::dispatch();
Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
if (! Cache::get('queue.heartbeat')) {
    throw new RuntimeException('Queue heartbeat missing.');
}
echo "MySQL, Redis and queued heartbeat integration passed.\n";
