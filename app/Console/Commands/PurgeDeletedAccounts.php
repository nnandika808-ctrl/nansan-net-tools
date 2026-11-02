<?php
namespace App\Console\Commands;
use App\Models\{User,MediaFile};
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Storage};
class PurgeDeletedAccounts extends Command
{
    protected $signature='nansan:purge-accounts {--dry-run}';
    protected $description='Hapus akun dengan permintaan terverifikasi yang melewati tenggang 30 hari';
    public function handle(): int
    {
        $ids=DB::table('deletion_requests')->whereNull('cancelled_at')->where('delete_after','<=',now())->pluck('user_id');
        if($this->option('dry-run')) { $this->info('Eligible accounts: '.$ids->count()); return self::SUCCESS; }
        foreach($ids as $id) DB::transaction(function () use ($id) {
            $pending=DB::table('deletion_requests')->where('user_id',$id)->whereNull('cancelled_at')->where('delete_after','<=',now())->lockForUpdate()->first();
            $user=User::whereKey($id)->where('status','deletion_pending')->where('role','user')->lockForUpdate()->first();
            if(!$pending||!$user) return;
            foreach(MediaFile::where('user_id',$id)->get() as $media) { Storage::disk($media->disk)->delete($media->path); $media->delete(); }
            DB::table('feedback_reports')->where('user_id',$id)->delete();
            DB::table('user_activity_logs')->where('user_id',$id)->delete();
            Audit::record('account.purged'); $user->delete();
        });
        $this->info('Account purge completed.'); return self::SUCCESS;
    }
}
