<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\GeneratorController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WorkspaceController;
use App\Models\CmsPage;
use App\Models\DocumentationArticle;
use App\Models\Tool;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/tools', [CatalogController::class, 'index'])->name('tools');
Route::get('/tools/{tool}', [CatalogController::class, 'show'])->name('tools.show');
Route::get('/search', [CatalogController::class, 'search'])->middleware('throttle:60,1');
Route::get('/docs', [DocumentationController::class, 'index']);
Route::get('/docs/{slug}', [DocumentationController::class, 'show']);
Route::get('/pages/{slug}', [DocumentationController::class, 'page']);
Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');

Route::middleware('guest')->group(function () {
    foreach (['login', 'register', 'recovery'] as $name) {
        Route::get('/'.$name, [AuthController::class, 'show'])->name($name);
    }
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/recovery', [AuthController::class, 'recover'])->middleware('throttle:recovery');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
    Route::post('/welcome/seen', [WorkspaceController::class, 'welcome']);
    Route::post('/favorites/{tool}', [WorkspaceController::class, 'favorite'])->middleware('throttle:writes');
    Route::get('/history', [WorkspaceController::class, 'histories']);
    Route::delete('/history/{history}', [WorkspaceController::class, 'deleteHistory']);
    Route::get('/history/{history}/download', [WorkspaceController::class, 'download']);
    Route::get('/presets', [WorkspaceController::class, 'presets']);
    Route::post('/tools/{tool}/presets', [WorkspaceController::class, 'storePreset'])->middleware('throttle:writes');
    Route::patch('/presets/{preset}', [WorkspaceController::class, 'updatePreset']);
    Route::delete('/presets/{preset}', [WorkspaceController::class, 'deletePreset']);
    Route::post('/tools/{tool}/generate', [GeneratorController::class, 'store'])->middleware('throttle:generate');
    Route::post('/announcements/{announcement}/read', [WorkspaceController::class, 'readAnnouncement']);
    Route::get('/account', [AccountController::class, 'index']);
    Route::patch('/account', [AccountController::class, 'update']);
    Route::patch('/account/preferences', [AccountController::class, 'preferences']);
    Route::put('/account/password', [AccountController::class, 'password'])->middleware('throttle:writes');
    Route::post('/account/recovery', [AccountController::class, 'recovery'])->middleware('throttle:recovery');
    Route::delete('/account/sessions/{session}', [AccountController::class, 'revokeSession'])->middleware('throttle:writes');
    Route::post('/account/deletion', [AccountController::class, 'deletion'])->middleware('throttle:writes');
    Route::post('/account/deletion/cancel', [AccountController::class, 'cancelDeletion'])->middleware('throttle:writes');
    Route::post('/account/avatar', [MediaController::class, 'avatar'])->middleware('throttle:writes');
    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::post('/feedback', [FeedbackController::class, 'store'])->middleware('throttle:writes');
});
Route::prefix('admin')->middleware(['auth', 'can:admin'])->group(function () {
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/tools/{tool}/versions', [VersionController::class, 'index']);
    Route::post('/tools/{tool}/versions', [VersionController::class, 'store']);
    Route::put('/tools/{tool}/versions/{id}', [VersionController::class, 'store']);
    Route::delete('/tools/{tool}/versions/{id}', [VersionController::class, 'destroy']);
    Route::get('/tools/{tool}/templates', [TemplateController::class, 'index']);
    Route::post('/tools/{tool}/templates', [TemplateController::class, 'store']);
    Route::put('/tools/{tool}/templates/{id}', [TemplateController::class, 'store']);
    Route::delete('/tools/{tool}/templates/{id}', [TemplateController::class, 'destroy']);
    Route::get('/faq', [FaqController::class, 'index']);
    Route::put('/faq', [FaqController::class, 'store']);
    Route::get('/users/{user}', [AdminController::class, 'showUser']);
    Route::get('/seo', [SeoController::class, 'index']);
    Route::post('/seo', [SeoController::class, 'store']);
    Route::delete('/seo/{id}', [SeoController::class, 'destroy']);
    Route::get('/', [AdminController::class, 'index']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
    Route::get('/tools', [AdminController::class, 'tools']);
    Route::patch('/tools/{tool}', [AdminController::class, 'updateTool']);
    Route::get('/tools/{tool}/preview', [GeneratorController::class, 'preview']);
    Route::post('/tools/{tool}/preview', [GeneratorController::class, 'previewOutput'])->middleware('throttle:generate');
    Route::get('/feedback', [AdminController::class, 'feedback']);
    Route::patch('/feedback/{feedback}', [AdminController::class, 'updateFeedback']);
    Route::get('/content/{kind}', [AdminController::class, 'content']);
    Route::post('/content/{kind}', [AdminController::class, 'saveContent']);
    Route::put('/content/{kind}/{id}', [AdminController::class, 'saveContent']);
    Route::delete('/content/{kind}/{id}', [AdminController::class, 'deleteContent']);
    Route::get('/media', [AdminController::class, 'media']);
    Route::post('/media', [MediaController::class, 'store']);
    Route::delete('/media/{media}', [MediaController::class, 'destroy']);
    Route::get('/logs', [AdminController::class, 'logs']);
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::put('/settings', [AdminController::class, 'saveSettings']);
    Route::get('/monitoring', [MonitoringController::class, 'index']);
    Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:1,5');
});
Route::get('/robots.txt', fn () => response(config('nansan.indexable') ? "User-agent: *\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /account\nDisallow: /history\nDisallow: /presets\nDisallow: /feedback\nSitemap: ".url('/sitemap.xml') : "User-agent: *\nDisallow: /", 200, ['Content-Type' => 'text/plain']));
Route::get('/sitemap.xml', function () {
    $urls = collect([url('/'), url('/tools'), url('/docs')])->merge(Tool::all()->map(fn ($t) => url('/tools/'.$t->slug)))
        ->merge(DocumentationArticle::published()->get()->map(fn ($a) => url('/docs/'.$a->slug)))
        ->merge(CmsPage::where('status', 'published')->get()->map(fn ($p) => url('/pages/'.$p->slug)));

    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls->map(fn ($url) => '<url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>')->implode('').'</urlset>', 200, ['Content-Type' => 'application/xml']);
});
