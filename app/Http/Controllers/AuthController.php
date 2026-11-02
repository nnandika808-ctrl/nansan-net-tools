<?php
namespace App\Http\Controllers;
use App\Http\Requests\{RegisterRequest,LoginRequest};
use App\Models\User;
use App\Services\{Audit,RecoveryCodes,Turnstile};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
class AuthController extends Controller
{
    public function show(Request $request) { return Inertia::render('Auth',['mode'=>$request->route()->getName(),'turnstileKey'=>config('nansan.turnstile.enabled')?config('nansan.turnstile.site_key'):null]); }
    public function register(RegisterRequest $request, Turnstile $turnstile, RecoveryCodes $recovery)
    {
        if (!$turnstile->verify($request->input('cf-turnstile-response'),$request->ip())) throw ValidationException::withMessages(['cf-turnstile-response'=>'Verifikasi keamanan gagal. Silakan coba lagi.']);
        [$user,$codes]=DB::transaction(function () use ($request,$recovery) {
            $user=User::create($request->safe()->only(['username','password']));
            return [$user,$recovery->issue($user)];
        });
        Auth::login($user); $request->session()->regenerate(); Audit::record('auth.register','user',$user->id);
        return redirect('/dashboard')->with('recoveryCodes',$codes)->with('success','Akun berhasil dibuat. Simpan kode pemulihan Anda.');
    }
    public function login(LoginRequest $request)
    {
        $user=User::where('username',$request->input('username'))->first();
        // The same hash work is performed for known and unknown usernames.
        $dummyHash=Hash::make(Str::random(32));
        $valid=Hash::check($request->input('password'),$user?->password??$dummyHash);
        if (!$user || !$valid || !in_array($user->status,['active','deletion_pending'],true)) {
            Audit::record('auth.login_failed');
            throw ValidationException::withMessages(['username'=>'Username atau password tidak sesuai.']);
        }
        $suspicious=app(\App\Services\LoginDetector::class)->record($user,$request);
        Auth::login($user,$request->boolean('remember')); $request->session()->regenerate();
        $user->forceFill(['last_login_at'=>now()])->save(); Audit::record('auth.login','user',$user->id,['suspicious'=>(bool)$suspicious]);
        return redirect()->intended($user->status==='deletion_pending'?'/account':'/dashboard');
    }
    public function logout(Request $request)
    {
        Audit::record('auth.logout'); Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect('/');
    }
    public function recover(Request $request)
    {
        $data=$request->validate(['username'=>['required','string','max:32'],'recovery_code'=>['required','string','size:32'],'password'=>['required','confirmed',Password::min(12)->letters()->numbers()]]);
        $success=DB::transaction(function () use ($data) {
            $user=User::where('username',strtolower($data['username']))->where('status','active')->lockForUpdate()->first();
            if (!$user) return false;
            $consumed=DB::table('recovery_codes')->where('user_id',$user->id)->where('code_hash',hash('sha256',$data['recovery_code']))->delete();
            if ($consumed!==1) return false;
            $user->forceFill(['password'=>$data['password'],'remember_token'=>Str::random(60)])->save();
            DB::table('sessions')->where('user_id',$user->id)->delete(); Audit::record('auth.recovered','user',$user->id);
            return true;
        });
        if (!$success) throw ValidationException::withMessages(['recovery_code'=>'Username atau kode pemulihan tidak sesuai.']);
        return redirect('/login')->with('success','Password diperbarui. Kode pemulihan tersebut sudah digunakan.');
    }
}
