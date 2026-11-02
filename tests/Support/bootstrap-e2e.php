<?php
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default')!=='sqlite' || basename(config('database.connections.sqlite.database'))!=='e2e.sqlite' || !app()->environment('local')) throw new RuntimeException('E2E setup is restricted to the isolated local e2e.sqlite database.');
$user=App\Models\User::firstOrCreate(['username'=>'e2e_admin'],['password'=>'E2E_Only_Password_12345','welcome_seen_at'=>now()]);
$user->forceFill(['role'=>'admin','welcome_seen_at'=>now()])->save();
echo "Isolated test admin prepared.\n";
