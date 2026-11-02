<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{CatalogController,AuthController,WorkspaceController,GeneratorController,AccountController,DocumentationController,AdminController,FeedbackController,MediaController,MonitoringController};

Route::get('/',[CatalogController::class,'home'])->name('home');
Route::get('/tools',[CatalogController::class,'index'])->name('tools');
Route::get('/tools/{tool}',[CatalogController::class,'show'])->name('tools.show');
Route::get('/search',[CatalogController::class,'search'])->middleware('throttle:60,1');
Route::get('/docs',[DocumentationController::class,'index']);
Route::get('/docs/{slug}',[DocumentationController::class,'show']);
Route::get('/pages/{slug}',[DocumentationController::class,'page']);
Route::get('/media/{media}',[MediaController::class,'show'])->name('media.show');

Route::middleware('guest')->group(function () {
    foreach(['login','register','recovery'] as $name) Route::get('/'.$name,[AuthController::class,'show'])->name($name);
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:login');
    Route::post('/register',[AuthController::class,'register'])->middleware('throttle:register');
    Route::post('/recovery',[AuthController::class,'recover'])->middleware('throttle:recovery');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout',[AuthController::class,'logout']);
    Route::get('/dashboard',[WorkspaceController::class,'index'])->name('dashboard');
    Route::post('/welcome/seen',[WorkspaceController::class,'welcome']);
    Route::post('/favorites/{tool}',[WorkspaceController::class,'favorite'])->middleware('throttle:writes');
    Route::get('/history',[WorkspaceController::class,'histories']);
    Route::delete('/history/{history}',[WorkspaceController::class,'deleteHistory']);
    Route::get('/history/{history}/download',[WorkspaceController::class,'download']);
    Route::get('/presets',[WorkspaceController::class,'presets']);
    Route::post('/tools/{tool}/presets',[WorkspaceController::class,'storePreset'])->middleware('throttle:writes');
    Route::patch('/presets/{preset}',[WorkspaceController::class,'updatePreset']);
    Route::delete('/presets/{preset}',[WorkspaceController::class,'deletePreset']);
    Route::post('/tools/{tool}/generate',[GeneratorController::class,'store'])->middleware('throttle:generate');
    Route::post('/announcements/{announcement}/read',[WorkspaceController::class,'readAnnouncement']);
    Route::get('/account',[AccountController::class,'index']);
    Route::patch('/account',[AccountController::class,'update']);
    Route::patch('/account/preferences',[AccountController::class,'preferences']);
    Route::put('/account/password',[AccountController::class,'password'])->middleware('throttle:writes');
    Route::post('/account/recovery',[AccountController::class,'recovery'])->middleware('throttle:recovery');
    Route::delete('/account/sessions/{session}',[AccountController::class,'revokeSession'])->middleware('throttle:writes');
    Route::post('/account/deletion',[AccountController::class,'deletion'])->middleware('throttle:writes');
    Route::post('/account/deletion/cancel',[AccountController::class,'cancelDeletion'])->middleware('throttle:writes');
    Route::post('/account/avatar',[MediaController::class,'avatar'])->middleware('throttle:writes');
    Route::get('/feedback',[FeedbackController::class,'index']);
    Route::post('/feedback',[FeedbackController::class,'store'])->middleware('throttle:writes');
});
Route::prefix('admin')->middleware(['auth','can:admin'])->group(function () {
    Route::get('/',[AdminController::class,'index']);
    Route::get('/users',[AdminController::class,'users']); Route::patch('/users/{user}',[AdminController::class,'updateUser']);
    Route::get('/tools',[AdminController::class,'tools']); Route::patch('/tools/{tool}',[AdminController::class,'updateTool']);
    Route::get('/tools/{tool}/preview',[GeneratorController::class,'preview']);
    Route::post('/tools/{tool}/preview',[GeneratorController::class,'previewOutput'])->middleware('throttle:generate');
    Route::get('/feedback',[AdminController::class,'feedback']); Route::patch('/feedback/{feedback}',[AdminController::class,'updateFeedback']);
    Route::get('/content/{kind}',[AdminController::class,'content']); Route::post('/content/{kind}',[AdminController::class,'saveContent']);
    Route::put('/content/{kind}/{id}',[AdminController::class,'saveContent']); Route::delete('/content/{kind}/{id}',[AdminController::class,'deleteContent']);
    Route::get('/media',[AdminController::class,'media']); Route::post('/media',[MediaController::class,'store']); Route::delete('/media/{media}',[MediaController::class,'destroy']);
    Route::get('/logs',[AdminController::class,'logs']); Route::get('/settings',[AdminController::class,'settings']); Route::put('/settings',[AdminController::class,'saveSettings']);
    Route::get('/monitoring',[MonitoringController::class,'index']);
    Route::post('/backups',[\App\Http\Controllers\BackupController::class,'store'])->middleware('throttle:1,5');
});
Route::get('/robots.txt',fn()=>response(config('nansan.indexable')?"User-agent: *\nDisallow: /admin\nDisallow: /dashboard\nDisallow: /account\nDisallow: /history\nDisallow: /presets\nDisallow: /feedback\nSitemap: ".url('/sitemap.xml'):"User-agent: *\nDisallow: /",200,['Content-Type'=>'text/plain']));
Route::get('/sitemap.xml',function () {
    $urls=collect([url('/'),url('/tools'),url('/docs')])->merge(\App\Models\Tool::all()->map(fn($t)=>url('/tools/'.$t->slug)))
        ->merge(\App\Models\DocumentationArticle::published()->get()->map(fn($a)=>url('/docs/'.$a->slug)))
        ->merge(\App\Models\CmsPage::where('status','published')->get()->map(fn($p)=>url('/pages/'.$p->slug)));
    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls->map(fn($url)=>'<url><loc>'.htmlspecialchars($url,ENT_XML1).'</loc></url>')->implode('').'</urlset>',200,['Content-Type'=>'application/xml']);
});
