<?php
namespace App\Http\Controllers;
use App\Services\{Audit,RecoveryCodes};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
class AccountController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Account',[
            'sessions'=>DB::table('sessions')->where('user_id',$request->user()->id)->get(['id','ip_address','user_agent','last_activity'])->map(fn($s)=>['id'=>hash_hmac('sha256',$s->id,config('app.key')),'ip'=>$s->ip_address,'agent'=>$s->user_agent,'last_activity'=>$s->last_activity,'current'=>hash_equals($s->id,$request->session()->getId())]),
            'deletion'=>DB::table('deletion_requests')->where('user_id',$request->user()->id)->whereNull('cancelled_at')->first(),
            'recoveryCodeCount'=>DB::table('recovery_codes')->where('user_id',$request->user()->id)->count(),
        ]);
    }
    public function update(Request $request)
    {
        $data=$request->validate(['username'=>['required','string','min:3','max:32','regex:/^[a-z0-9_]+$/','unique:users,username,'.$request->user()->id],'name'=>['nullable','string','max:100'],'locale'=>['required','in:id,en'],'theme'=>['required','in:dark,light,system']]);
        $request->user()->update($data); Audit::record('account.updated'); return back()->with('success','Profil diperbarui.');
    }
    public function preferences(Request $request)
    {
        $data=$request->validate(['locale'=>['required','in:id,en'],'theme'=>['required','in:dark,light,system']]);
        $request->user()->update($data); return back();
    }
    public function password(Request $request)
    {
        $data=$request->validate(['current_password'=>['required','current_password'],'password'=>['required','confirmed',Password::min(12)->letters()->numbers()]]);
        $request->user()->forceFill(['password'=>$data['password'],'remember_token'=>Str::random(60)])->save();
        DB::table('sessions')->where('user_id',$request->user()->id)->where('id','!=',$request->session()->getId())->delete();
        $request->session()->regenerate(); Audit::record('account.password_changed'); return back()->with('success','Password diperbarui. Sesi lain telah dicabut.');
    }
    public function recovery(Request $request, RecoveryCodes $codes)
    {
        $request->validate(['password'=>['required','current_password']]);
        Audit::record('account.recovery_rotated'); return back()->with('recoveryCodes',$codes->issue($request->user()))->with('success','Simpan kode pemulihan baru. Kode lama tidak berlaku.');
    }
    public function revokeSession(Request $request, string $session)
    {
        $request->validate(['password'=>['required','current_password']]);
        $sessions=DB::table('sessions')->where('user_id',$request->user()->id)->where('id','!=',$request->session()->getId())->get(['id']);
        $found=$sessions->first(fn($s)=>hash_equals(hash_hmac('sha256',$s->id,config('app.key')),$session)); abort_unless($found,404);
        DB::table('sessions')->where('id',$found->id)->delete();
        $request->user()->forceFill(['remember_token'=>Str::random(60)])->save(); Audit::record('account.session_revoked'); return back()->with('success','Sesi dicabut.');
    }
    public function deletion(Request $request)
    {
        $request->validate(['password'=>['required','current_password'],'confirmation'=>['required','in:HAPUS']]);
        abort_if($request->user()->isAdmin(),422,'Alihkan tanggung jawab Admin sebelum menghapus akun.');
        DB::transaction(function () use ($request) {
            $request->user()->forceFill(['status'=>'deletion_pending','remember_token'=>Str::random(60)])->save();
            DB::table('deletion_requests')->updateOrInsert(['user_id'=>$request->user()->id],['requested_at'=>now(),'delete_after'=>now()->addDays(30),'cancelled_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('sessions')->where('user_id',$request->user()->id)->where('id','!=',$request->session()->getId())->delete(); Audit::record('account.deletion_requested');
        });
        return back()->with('success','Akun dinonaktifkan. Anda dapat membatalkan penghapusan selama 30 hari.');
    }
    public function cancelDeletion(Request $request)
    {
        $request->validate(['password'=>['required','current_password']]);
        DB::transaction(function () use ($request) {
            $pending=DB::table('deletion_requests')->where('user_id',$request->user()->id)->whereNull('cancelled_at')->where('delete_after','>',now())->lockForUpdate()->first(); abort_unless($pending,422);
            DB::table('deletion_requests')->where('id',$pending->id)->update(['cancelled_at'=>now(),'updated_at'=>now()]);
            $request->user()->forceFill(['status'=>'active'])->save(); Audit::record('account.deletion_cancelled');
        });
        return back()->with('success','Penghapusan dibatalkan. Akun aktif kembali.');
    }
}
