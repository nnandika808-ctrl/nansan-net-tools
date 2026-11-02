<?php

namespace App\Providers;

use App\Models\FeedbackReport;
use App\Models\MediaFile;
use App\Models\ScriptHistory;
use App\Models\User;
use App\Models\UserPreset;
use App\Policies\OwnedRecordPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        foreach ([UserPreset::class, ScriptHistory::class, FeedbackReport::class, MediaFile::class] as $model) {
            Gate::policy($model, OwnedRecordPolicy::class);
        }
        RateLimiter::for('login', fn ($request) => Limit::perMinute(5)->by(hash('sha256', strtolower((string) $request->input('username')).'|'.$request->ip())));
        RateLimiter::for('register', fn ($request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('recovery', fn ($request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('generate', fn ($request) => Limit::perMinute(30)->by($request->user()->id));
        RateLimiter::for('writes', fn ($request) => Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()));
    }
}
