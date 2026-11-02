<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class RecoveryCodes
{
    public function issue(User $user): array
    {
        return DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            DB::table('recovery_codes')->where('user_id',$user->id)->delete();
            $codes=[];
            for ($i=0;$i<8;$i++) {
                $code=bin2hex(random_bytes(16)); $codes[]=$code;
                DB::table('recovery_codes')->insert(['user_id'=>$user->id,'code_hash'=>hash('sha256',$code),'created_at'=>now()]);
            }
            return $codes;
        });
    }
}
