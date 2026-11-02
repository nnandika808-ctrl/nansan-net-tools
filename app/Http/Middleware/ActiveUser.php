<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user=$request->user();
        if ($user && $user->status==='suspended') { Auth::logout(); $request->session()->invalidate(); return redirect('/login'); }
        if ($user && $user->status==='deletion_pending' && !$request->is('account*','logout','media/*')) return redirect('/account');
        return $next($request);
    }
}
