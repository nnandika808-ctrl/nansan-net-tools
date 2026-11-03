<?php

use App\Models\User;
use App\Services\Audit;

ob_start();
require __DIR__.'/local-preview-state.php';
ob_end_clean();
$owner = User::where('username', 'nansan_preview')->where('role', 'admin')->firstOrFail();
$owner->update(['name' => 'Nansan', 'theme' => 'light']);
Audit::record('operator.preview_appearance_initialized', 'user', $owner->id);
echo "Local design preview appearance initialized.\n";
