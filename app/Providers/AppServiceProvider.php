<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::define('admin', fn (\App\Models\User $user) => $user->isAdmin());
        foreach ([\App\Models\UserPreset::class,\App\Models\ScriptHistory::class,\App\Models\FeedbackReport::class,\App\Models\MediaFile::class] as $model) {
            \Illuminate\Support\Facades\Gate::policy($model,\App\Policies\OwnedRecordPolicy::class);
        }
        \Illuminate\Support\Facades\RateLimiter::for('login',fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(hash('sha256',strtolower((string)$request->input('username')).'|'.$request->ip())));
        \Illuminate\Support\Facades\RateLimiter::for('register',fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perHour(10)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('recovery',fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by($request->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('generate',fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->user()->id));
        \Illuminate\Support\Facades\RateLimiter::for('writes',fn($request)=>\Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->user()?->id??$request->ip()));
    }
}
