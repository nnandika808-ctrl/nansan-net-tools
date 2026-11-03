<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = realpath(config('database.connections.sqlite.database'));
$root = realpath(dirname(__DIR__));
if (PHP_SAPI !== 'cli' || ! app()->environment('local') || config('database.default') !== 'sqlite' || ! $database || ! str_starts_with($database, $root.DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('The design preview requires an existing local SQLite database inside this project.');
}
echo json_encode(['database' => $database, 'admin_count' => User::where('role', 'admin')->count(), 'owner_exists' => User::where('username', 'nansan_preview')->where('role', 'admin')->exists()], JSON_THROW_ON_ERROR);
