<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CountPublicPageView
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $path = '/'.trim($request->path(), '/');
        $public = in_array($path, ['/', '/tools', '/docs'], true) || preg_match('~^/(tools|docs|pages)/[a-z0-9-]+$~D', $path);
        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && $public && ! str_contains(strtolower($request->header('Purpose', '')), 'prefetch') && ! $request->hasHeader('X-Inertia-Prefetch')) {
            try {
                $key = ['day' => now()->toDateString(), 'path' => $path];
                DB::table('page_view_daily')->insertOrIgnore($key + ['views' => 0]);
                DB::table('page_view_daily')->where($key)->increment('views');
            } catch (QueryException $error) {
                report($error);
            }
        }

        return $response;
    }
}
