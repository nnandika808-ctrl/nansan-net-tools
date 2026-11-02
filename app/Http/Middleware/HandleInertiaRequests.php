<?php
namespace App\Http\Middleware;
use Illuminate\Http\Request;
use Inertia\Middleware;
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';
    public function share(Request $request): array
    {
        return [...parent::share($request),
            'auth'=>['user'=>$request->user()?->only('id','username','name','role','status','locale','theme','avatar_path')],
            'flash'=>['success'=>fn()=>$request->session()->get('success'),'error'=>fn()=>$request->session()->get('error')],
            'recoveryCodes'=>fn()=>$request->session()->get('recoveryCodes'),
            'seo'=>['title'=>'NANSAN NET Tools','description'=>'Platform generator konfigurasi MikroTik. Gratis untuk komunitas jaringan.','canonical'=>url($request->path()),'indexable'=>(bool)config('nansan.indexable')&&!$request->user()],
        ];
    }
}
