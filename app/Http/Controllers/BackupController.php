<?php
namespace App\Http\Controllers;
use App\Services\Audit;
class BackupController extends Controller
{
    public function store()
    {
        abort_unless(config('nansan.backup_disk')&&config('nansan.backup_key'),422,'Konfigurasi penyimpanan dan kunci backup privat terlebih dahulu.');
        \App\Jobs\CreateBackup::dispatch();Audit::record('backup.requested');return back()->with('success','Permintaan backup masuk antrean. Periksa hasil verifikasi pada monitoring.');
    }
}
