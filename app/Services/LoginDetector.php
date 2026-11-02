<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class LoginDetector
{
    public function record(User $user,Request $request): bool
    {
        $previous=DB::table('user_activity_logs')->where('user_id',$user->id)->where('action','auth.login')->latest('id')->value('metadata');
        $previous=$previous?json_decode($previous,true):null;
        $current=['network'=>hash_hmac('sha256',$request->ip(),config('app.key')),'device'=>hash_hmac('sha256',(string)$request->userAgent(),config('app.key'))];
        $suspicious=$previous&&($previous['network']!==$current['network']||$previous['device']!==$current['device']);
        DB::table('user_activity_logs')->insert(['user_id'=>$user->id,'action'=>'auth.login','metadata'=>json_encode($current),'created_at'=>now()]);
        return (bool)$suspicious;
    }
}
