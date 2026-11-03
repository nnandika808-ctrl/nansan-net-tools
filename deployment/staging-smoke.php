<?php

use App\Generators\Modules\RoutingGamesModule;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Operator-only CLI helper. Never exposed through the web root or production.
$root = '/www/wwwroot/nansan-tools-staging/current';
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('staging') || config('database.connections.mysql.database') !== 'nansan_tools_staging' || config('app.url') !== 'http://127.0.0.1:8091') {
    throw new RuntimeException('Private staging guard failed.');
}
$action = $argv[1] ?? '';
if ($action === 'owner-state') {
    echo json_encode(['username_taken' => User::where('username', 'nansan')->exists(), 'admin_count' => User::where('role', 'admin')->count()], JSON_THROW_ON_ERROR);
} elseif ($action === 'start') {
    $username = 'smoke_'.bin2hex(random_bytes(8));
    $password = bin2hex(random_bytes(24));
    $user = User::create(['username' => $username, 'password' => $password, 'name' => 'Temporary staging smoke test']);
    $user->forceFill(['role' => 'admin'])->save();
    // Consumed only by the encrypted operator connection, never printed to logs.
    echo json_encode(['id' => $user->id, 'username' => $username, 'password' => $password], JSON_THROW_ON_ERROR);
} elseif ($action === 'cleanup') {
    $identity = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $user = User::findOrFail($identity['id']);
    if (! str_starts_with($user->username, 'smoke_') || $user->username !== $identity['username'] || $user->name !== 'Temporary staging smoke test') {
        throw new RuntimeException('Smoke identity ownership mismatch.');
    }
    DB::table('sessions')->where('user_id', $user->id)->delete();
    $user->delete();
    echo '{"temporary_identity_removed":true}';
} elseif ($action === 'game-port-audit') {
    $cases = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($cases) || count($cases) > 226) {
        throw new RuntimeException('Invalid game audit input.');
    }
    $module = new RoutingGamesModule;
    $results = [];
    foreach ($cases as $case) {
        try {
            $results[] = ['id' => $case['id'], 'status' => 200, 'script' => $module->generate($case['input'])->text()];
        } catch (HttpException $error) {
            $results[] = ['id' => $case['id'], 'status' => $error->getStatusCode(), 'message' => $error->getMessage()];
        }
    }
    echo json_encode(['release' => basename(realpath($root)), 'cases' => $results], JSON_THROW_ON_ERROR);
} elseif ($action === 'operations') {
    Artisan::call('nansan:doctor', ['--queue' => true]);
    $doctor = trim(Artisan::output());
    $backup = app(BackupService::class);
    $id = $backup->create();
    $backup->verifyRestore($id);
    $record = DB::table('backup_records')->find($id);
    echo json_encode(['doctor' => json_decode($doctor, true, 512, JSON_THROW_ON_ERROR), 'backup' => ['status' => $record->status, 'restore_tested_at' => $record->restore_tested_at, 'bytes' => $record->size], 'off_host_backup' => false], JSON_THROW_ON_ERROR);
} else {
    throw new RuntimeException('Unsupported smoke operation.');
}
