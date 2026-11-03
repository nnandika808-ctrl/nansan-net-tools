<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class HostingReadiness
{
    public function check(bool $production, bool $runtime): array
    {
        $checks = [];
        $add = function (string $name, bool $passed, string $hint) use (&$checks): void {
            $checks[$name] = ['passed' => $passed, 'hint' => $hint];
        };
        $add('php', PHP_VERSION_ID >= 80400, 'PHP 8.4+; check both CLI and site FPM.');
        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'intl', 'mbstring', 'openssl', 'pdo', 'session', 'sodium', 'tokenizer', 'xml', 'zip'] as $extension) {
            $add('extension_'.$extension, extension_loaded($extension), 'Enable required PHP extension in the selected PHP version.');
        }
        foreach (['storage', 'bootstrap/cache'] as $directory) {
            $add('writable_'.$directory, is_dir(base_path($directory)) && is_writable(base_path($directory)), 'Grant the app user write access only to runtime directories.');
        }
        $add('production_assets', $this->assetsPresent(), 'Build Vite assets and remove public/hot.');
        if ($production) {
            $add('unprivileged_runtime_user', function_exists('posix_geteuid') && posix_geteuid() !== 0, 'Run acceptance checks as the actual unprivileged PHP/worker user.');
            $host = parse_url(config('app.url'), PHP_URL_HOST);
            $add('environment', app()->environment('production') && ! config('app.debug'), 'APP_ENV=production and APP_DEBUG=false.');
            $add('https_origin', parse_url(config('app.url'), PHP_URL_SCHEME) === 'https' && (bool) $host, 'Set the real HTTPS APP_URL; validate TLS and proxy headers externally.');
            $key = config('app.key');
            $decoded = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
            $add('application_key', is_string($decoded) && strlen($decoded) === 32, 'Generate APP_KEY once; retain it across updates.');
            $add('sessions', config('session.driver') === 'database' && config('session.secure') === true && config('session.http_only') === true && config('session.encrypt') === true && config('session.same_site') === 'lax', 'Use encrypted database sessions and secure HttpOnly cookies.');
            $add('mysql', config('database.default') === 'mysql' && extension_loaded('pdo_mysql') && filled(config('database.connections.mysql.password')), 'Configure a dedicated MySQL/MariaDB database and app account.');
            $add('redis_cache', config('cache.default') === 'redis', 'Use a private Redis cache.');
            $queue = config('queue.default');
            $add('queue', $queue === 'redis' && (int) config('queue.connections.redis.retry_after') > 610, 'Redis retry_after must exceed the 610-second worker timeout (recommended 900).');
            $turnstile = config('nansan.turnstile');
            $add('turnstile', ($turnstile['enabled'] ?? false) === true && filled($turnstile['site_key'] ?? null) && filled($turnstile['secret'] ?? null) && in_array($host, $turnstile['hostnames'] ?? [], true), 'Set real Turnstile keys and the exact registration hostname.');
            $backupKey = base64_decode((string) config('nansan.backup_key'), true);
            $disk = config('nansan.backup_disk');
            $backupDisk = $disk ? config('filesystems.disks.'.$disk) : null;
            $private = is_array($backupDisk) && ($backupDisk['visibility'] ?? null) === 'private';
            if ($private && ($backupDisk['driver'] ?? '') === 'local') {
                $root = realpath($backupDisk['root'] ?? '');
                $public = realpath(public_path());
                $private = $root && $public && $root !== $public && ! str_starts_with($root, $public.DIRECTORY_SEPARATOR) && is_writable($root);
            }
            $add('private_backups', $private && is_string($backupKey) && strlen($backupKey) === 32, 'Configure a private backup destination and an independent base64 32-byte backup key.');
            $restore = config('nansan.restore_mysql');
            $add('restore_account', filled($restore['host'] ?? null) && filled($restore['username'] ?? null) && filled($restore['password'] ?? null) && $restore['username'] !== config('database.connections.mysql.username'), 'Use a separate account restricted to scratch databases nansan_restore_%.');
            foreach (['proc_open', 'proc_close', 'pcntl_alarm', 'pcntl_async_signals'] as $function) {
                $add('function_'.$function, function_exists($function), 'Allow process functions and CLI pcntl for backups and worker timeouts.');
            }
        }
        if ($runtime) {
            $this->runtime($add, $production);
        }

        return $checks;
    }

    private function assetsPresent(): bool
    {
        if (is_file(public_path('hot'))) {
            return false;
        }
        $manifest = json_decode(@file_get_contents(public_path('build/manifest.json')) ?: '', true);
        if (! is_array($manifest) || ! isset($manifest['resources/js/app.tsx']['file'])) {
            return false;
        }
        foreach ($manifest as $entry) {
            foreach (array_merge([$entry['file'] ?? ''], $entry['css'] ?? [], $entry['assets'] ?? []) as $file) {
                if (! is_string($file) || ! $file || str_contains($file, '..') || ! is_file(public_path('build/'.$file))) {
                    return false;
                }
            }
        }

        return true;
    }

    private function runtime(callable $add, bool $production): void
    {
        try {
            DB::select('select 1');
            $add('database_connection', true, 'Database is reachable.');
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_diff(array_keys($files), $migrator->getRepository()->getRan());
            $add('migrations', ! $pending, 'Apply pending migrations after a verified backup.');
            $add('initial_admin', DB::table('users')->where('role', 'admin')->where('status', 'active')->exists(), 'Create the owner through nansan:admin hidden prompts.');
            if ($production) {
                $add('recent_verified_backup', DB::table('backup_records')->where('status', 'restore_verified')->where('updated_at', '>=', now()->subDay())->exists(), 'Create and scratch-restore a fresh encrypted backup.');
            }
        } catch (\Throwable) {
            $add('database_connection', false, 'Check credentials, schema and permissions; no exception details are printed.');
        }
        try {
            Redis::connection()->ping();
            $add('redis_connection', true, 'Private Redis connection is reachable.');
        } catch (\Throwable) {
            $add('redis_connection', false, 'Check Redis endpoint and credentials.');
        }
        $key = 'hosting-check.'.Str::uuid();
        try {
            Cache::put($key, 'probe', 15);
            $add('cache_roundtrip', Cache::get($key) === 'probe', 'A unique short-lived cache probe must round-trip.');
            foreach (['scheduler.heartbeat' => 180, 'queue.heartbeat' => 600] as $heartbeat => $age) {
                $stamp = Cache::get($heartbeat);
                $time = $stamp ? Carbon::parse($stamp) : null;
                $add($heartbeat, $time && $time->lessThanOrEqualTo(now()) && $time->diffInSeconds(now(), true) < $age, 'Run the minute scheduler and persistent queue worker. Also run nansan:doctor --queue.');
            }
        } catch (\Throwable) {
            $add('cache_roundtrip', false, 'Check cache and background service access.');
        } finally {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // A failed backend is already reported without exposing its exception.
            }
        }
    }
}
