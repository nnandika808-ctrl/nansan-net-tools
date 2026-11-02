<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Services\Audit;
use App\Services\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SeoController extends Controller
{
    public function index(Seo $seo)
    {
        return Inertia::render('Seo', ['records' => DB::table('seo_metadata')->orderBy('path')->get(), 'paths' => $seo->paths(), 'images' => MediaFile::where('is_public', true)->get(['id', 'original_name'])]);
    }

    public function store(Request $request, Seo $seo)
    {
        $data = $request->validate(['path' => ['required', Rule::in($seo->paths())], 'title' => ['required', 'string', 'max:180'], 'description' => ['required', 'string', 'max:500'], 'noindex' => ['required', 'boolean'], 'image_id' => ['nullable', 'integer', Rule::exists('media_files', 'id')->where('is_public', true)]]);
        $record = ['title' => $data['title'], 'description' => $data['description'], 'noindex' => $data['noindex'], 'og_image' => isset($data['image_id']) ? '/media/'.$data['image_id'] : null, 'updated_at' => now()];
        DB::table('seo_metadata')->updateOrInsert(['path' => $data['path']], $record + ['created_at' => now()]);
        Audit::record('admin.seo_saved');

        return back()->with('success', 'Metadata SEO disimpan.');
    }

    public function destroy(int $id)
    {
        abort_unless(DB::table('seo_metadata')->where('id', $id)->exists(), 404);
        DB::table('seo_metadata')->where('id', $id)->delete();
        Audit::record('admin.seo_deleted', 'seo', $id);

        return back();
    }
}
