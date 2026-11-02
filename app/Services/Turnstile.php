<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
class Turnstile
{
    public function verify(?string $token, string $ip): bool
    {
        if (!config('nansan.turnstile.enabled')) return app()->environment(['local','testing']);
        if (!$token || !config('nansan.turnstile.secret')) return false;
        try {
            $response=Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify',[
                'secret'=>config('nansan.turnstile.secret'),'response'=>$token,'remoteip'=>$ip,
            ]);
            $body=$response->json();
            return $response->successful() && ($body['success']??false) === true
                && in_array($body['hostname']??'',config('nansan.turnstile.hostnames'),true)
                && ($body['action']??'') === 'register';
        } catch (\Throwable) { return false; }
    }
}
