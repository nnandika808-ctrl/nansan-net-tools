<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use App\Services\HostingReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostingReadinessTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publicDirectory = storage_path('framework/testing/hosting-'.Str::uuid());
        File::makeDirectory($this->publicDirectory.'/build/assets', 0755, true);
        $this->app->usePublicPath($this->publicDirectory);
        File::put($this->publicDirectory.'/build/assets/app.js', '/* isolated build fixture */');
        File::put($this->publicDirectory.'/build/manifest.json', json_encode(['resources/js/app.tsx' => ['file' => 'assets/app.js']]));
    }

    protected function tearDown(): void
    {
        if (isset($this->publicDirectory) && str_starts_with($this->publicDirectory, storage_path('framework/testing/hosting-'))) {
            File::deleteDirectory($this->publicDirectory);
        }
        parent::tearDown();
    }

    public function test_missing_hashed_asset_or_vite_hot_marker_blocks_release(): void
    {
        $check = app(HostingReadiness::class);
        $this->assertTrue($check->check(false, false)['production_assets']['passed']);
        File::delete($this->publicDirectory.'/build/assets/app.js');
        $this->assertFalse($check->check(false, false)['production_assets']['passed']);
        File::put($this->publicDirectory.'/build/assets/app.js', '/* restored fixture */');
        File::put($this->publicDirectory.'/hot', 'http://localhost:5173');
        $this->assertFalse($check->check(false, false)['production_assets']['passed']);
    }

    public function test_unsafe_production_configuration_fails_without_printing_secrets(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://example.test', 'database.connections.mysql.password' => 'NeverPrintDatabaseSecret', 'nansan.turnstile.secret' => 'NeverPrintTurnstileSecret', 'nansan.turnstile.site_key' => null, 'nansan.backup_key' => 'NeverPrintBackupSecret', 'queue.default' => 'sync']);
        $this->assertSame(1, Artisan::call('nansan:hosting-check', ['--production' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['ready']);
        foreach (['environment', 'https_origin', 'queue', 'turnstile', 'private_backups'] as $check) {
            $this->assertFalse($report['checks'][$check]['passed']);
        }
        foreach (['NeverPrintDatabaseSecret', 'NeverPrintTurnstileSecret', 'NeverPrintBackupSecret'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_public_backup_directory_is_rejected_even_with_a_valid_key(): void
    {
        config(['nansan.backup_key' => base64_encode(random_bytes(32)), 'nansan.backup_disk' => 'backups', 'filesystems.disks.backups' => ['driver' => 'local', 'root' => $this->publicDirectory, 'visibility' => 'private']]);
        $this->assertFalse(app(HostingReadiness::class)->check(true, false)['private_backups']['passed']);
    }

    public function test_default_queue_retry_window_exceeds_long_backup_and_worker_timeout(): void
    {
        $this->assertGreaterThan(610, config('queue.connections.redis.retry_after'));
        $this->assertGreaterThan(610, config('queue.connections.database.retry_after'));
        $this->assertGreaterThan((new CreateBackup)->timeout, config('queue.connections.redis.retry_after'));
    }

    public function test_synchronous_jobs_cannot_be_reported_as_worker_evidence(): void
    {
        config(['queue.default' => 'sync']);
        Redis::shouldReceive('connection->ping')->once()->andReturn('PONG');
        Cache::put('scheduler.heartbeat', now()->toIso8601String());
        $this->assertSame(1, Artisan::call('nansan:doctor', ['--queue' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('unavailable', $report['queue']);
        $this->assertNotSame('worker_processed_unique_probe', $report['queue']);
    }

    public function test_future_heartbeats_and_failed_backend_never_pass_runtime_checks(): void
    {
        Redis::shouldReceive('connection->ping')->once()->andThrow(new \RuntimeException('NeverPrintRedisSecret'));
        Cache::put('scheduler.heartbeat', now()->addDay()->toIso8601String());
        Cache::put('queue.heartbeat', now()->subHour()->toIso8601String());
        $report = app(HostingReadiness::class)->check(false, true);
        $this->assertFalse($report['redis_connection']['passed']);
        $this->assertFalse($report['scheduler.heartbeat']['passed']);
        $this->assertFalse($report['queue.heartbeat']['passed']);
        $this->assertFalse($report['initial_admin']['passed']);
        $this->assertStringNotContainsString('NeverPrintRedisSecret', json_encode($report));
    }
}
