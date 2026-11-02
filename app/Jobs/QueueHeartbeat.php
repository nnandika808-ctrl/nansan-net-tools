<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;
    public function __construct(public string $key='queue.heartbeat') {}
    public function handle(): void { Cache::put($this->key??'queue.heartbeat',now()->toIso8601String(),600); }
}
