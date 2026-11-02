<?php
// Operator-only CLI helper. Never exposed through the web root or production.
$root = '/www/wwwroot/nansan-tools-staging/current';
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('staging') || config('database.connections.mysql.database') !== 'nansan_tools_staging' || config('app.url') !== 'http://127.0.0.1:8091') throw new RuntimeException('Private staging guard failed.');
$action = $argv[1] ?? '';
if ($action === 'start') {
    $username = 'smoke_'.bin2hex(random_bytes(8)); $password = bin2hex(random_bytes(24));
    $user = App\Models\User::create(['username'=>$username,'password'=>$password,'name'=>'Temporary staging smoke test']);
    $user->forceFill(['role'=>'admin'])->save();
    // Consumed only by the encrypted operator connection, never printed to logs.
    echo json_encode(['id'=>$user->id,'username'=>$username,'password'=>$password],JSON_THROW_ON_ERROR);
} elseif ($action === 'cleanup') {
    $identity = json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    $user = App\Models\User::findOrFail($identity['id']);
    if (!str_starts_with($user->username,'smoke_') || $user->username!==$identity['username'] || $user->name!=='Temporary staging smoke test') throw new RuntimeException('Smoke identity ownership mismatch.');
    Illuminate\Support\Facades\DB::table('sessions')->where('user_id',$user->id)->delete();
    $user->delete();echo '{"temporary_identity_removed":true}';
} elseif ($action === 'operations') {
    Illuminate\Support\Facades\Artisan::call('nansan:doctor',['--queue'=>true]);
    $doctor = trim(Illuminate\Support\Facades\Artisan::output());
    $backup = app(App\Services\BackupService::class);$id=$backup->create();$backup->verifyRestore($id);
    $record=Illuminate\Support\Facades\DB::table('backup_records')->find($id);
    echo json_encode(['doctor'=>json_decode($doctor,true,512,JSON_THROW_ON_ERROR),'backup'=>['status'=>$record->status,'restore_tested_at'=>$record->restore_tested_at,'bytes'=>$record->size],'off_host_backup'=>false],JSON_THROW_ON_ERROR);
} else throw new RuntimeException('Unsupported smoke operation.');
