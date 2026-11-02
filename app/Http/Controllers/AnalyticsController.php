<?php

namespace App\Http\Controllers;

use App\Models\Tool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $from = now()->subDays(29)->toDateString();
        $views = DB::table('page_view_daily')->where('day', '>=', $from);

        return Inertia::render('Analytics', [
            'totals' => ['page_views_30_days' => (int) (clone $views)->sum('views'), 'new_users_30_days' => User::where('created_at', '>=', $from)->count(), 'generated_total' => (int) Tool::sum('usage_count')],
            'daily' => (clone $views)->select('day')->selectRaw('SUM(views) as views')->groupBy('day')->orderBy('day')->get(),
            'paths' => (clone $views)->select('path')->selectRaw('SUM(views) as views')->groupBy('path')->orderByDesc('views')->limit(10)->get(),
            'tools' => Tool::orderByDesc('usage_count')->get(['name', 'usage_count']),
        ]);
    }
}
