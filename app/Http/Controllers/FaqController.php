<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FaqController extends Controller
{
    public function index()
    {
        return Inertia::render('Faq', ['items' => SiteSetting::where('key', 'faq')->value('value') ?? []]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['items' => ['present', 'array', 'max:24'], 'items.*.locale' => ['required', 'in:id,en'], 'items.*.question' => ['required', 'string', 'max:180'], 'items.*.answer' => ['required', 'string', 'max:1200']]);
        SiteSetting::updateOrCreate(['key' => 'faq'], ['value' => $data['items']]);
        Audit::record('admin.faq_saved');

        return back()->with('success', 'FAQ disimpan.');
    }
}
