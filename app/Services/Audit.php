<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class Audit
{
    public static function record(string $action, ?string $subject = null, ?int $id = null, array $metadata = []): void
    {
        // Only explicitly approved low sensitivity fields enter the log.
        $safe = array_intersect_key($metadata, array_flip(['status','previous_status','version','count','resource','suspicious']));
        DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>$subject,'subject_id'=>$id,'metadata'=>json_encode($safe),'created_at'=>now()]);
    }
}
