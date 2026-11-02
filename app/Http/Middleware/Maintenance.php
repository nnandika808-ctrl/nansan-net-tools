<?php
namespace App\Http\Middleware;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
class Maintenance
{
    public function handle(Request $request, Closure $next)
    {
        $setting=SiteSetting::where('key','maintenance')->value('value');
        if (($setting['enabled']??false) && !$request->user()?->isAdmin() && !$request->is('login','logout','account*','up')) {
            if($request->expectsJson()) return response()->json(['message'=>$setting['message']??'Pemeliharaan'],503)->header('Retry-After','300');
            return Inertia::render('Maintenance',['message'=>$setting['message']??'Sedang dalam pemeliharaan.','until'=>$setting['until']??null])->toResponse($request)->setStatusCode(503);
        }
        return $next($request);
    }
}
