<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\CmsPage;
use App\Models\DocumentationArticle;
use App\Models\FeedbackReport;
use App\Models\MediaFile;
use App\Models\SiteSetting;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolTag;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin', ['section' => 'overview', 'stats' => [
            'users' => User::count(), 'active' => User::where('status', 'active')->count(), 'new' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'tools' => Tool::count(), 'generated' => (int) Tool::sum('usage_count'), 'feedback' => FeedbackReport::where('status', 'open')->count(),
        ], 'records' => DB::table('audit_logs')->latest('id')->limit(20)->get()]);
    }

    public function users(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,suspended,deletion_pending']]);

        return Inertia::render('Admin', ['section' => 'users', 'filters' => $request->only('q', 'status'), 'records' => User::when($request->q, fn ($q, $term) => $q->where('username', 'like', "%{$term}%"))->when($request->status, fn ($q, $status) => $q->where('status', $status))->withCount(['histories', 'presets'])->latest()->paginate(20)]);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        abort_if($user->id === $request->user()->id || $user->role === 'admin', 422, 'Status Admin hanya dapat diubah melalui prosedur operator.');
        abort_if($user->status === 'deletion_pending', 422, 'Penghapusan hanya dapat dibatalkan oleh pemilik akun.');
        $previous = $user->status;
        $user->forceFill($data)->save();
        if ($data['status'] === 'suspended') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        }
        Audit::record('admin.user_status', 'user', $user->id, ['previous_status' => $previous, 'status' => $data['status']]);

        return back()->with('success', 'Status pengguna diperbarui.');
    }

    public function showUser(User $user)
    {
        return Inertia::render('AdminUser', ['member' => $user->only('id', 'username', 'name', 'role', 'status', 'locale', 'theme', 'created_at', 'last_login_at'),
            'counts' => ['history' => $user->histories()->count(), 'presets' => $user->presets()->count(), 'feedback' => $user->feedback()->count(), 'sessions' => DB::table('sessions')->where('user_id', $user->id)->count()],
            'activity' => DB::table('user_activity_logs')->where('user_id', $user->id)->latest('id')->limit(30)->get(['id', 'action', 'created_at']),
            'audit' => DB::table('audit_logs')->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($s) => $s->where('subject_type', 'user')->where('subject_id', $user->id)))->latest('id')->limit(30)->get(['id', 'action', 'created_at'])]);
    }

    public function tools()
    {
        return Inertia::render('Admin', ['section' => 'tools', 'records' => Tool::with(['category', 'synonyms', 'tags'])->orderBy('sort_order')->get(), 'categories' => ToolCategory::all()]);
    }

    public function updateTool(Request $request, Tool $tool)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'max:2000'], 'description_en' => ['nullable', 'string', 'max:2000'], 'featured' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:999'], 'tool_category_id' => ['required', 'exists:tool_categories,id'], 'synonyms' => ['array', 'max:20'], 'synonyms.*' => ['string', 'max:100'], 'tags' => ['array', 'max:12'], 'tags.*' => ['required', 'string', 'max:40'], 'icon' => ['sometimes', Rule::in(['route', 'queue', 'pcc', 'starlink', 'games'])], 'status' => ['prohibited'], 'handler' => ['prohibited'], 'code' => ['prohibited']]);
        DB::transaction(function () use ($tool, $data) {
            $tool->update(collect($data)->except(['synonyms', 'tags'])->all());
            if (isset($data['tags'])) {
                $tool->tags()->sync(collect(array_unique($data['tags']))->map(fn ($tag) => ToolTag::firstOrCreate(['name' => $tag])->id)->all());
            }
            if (isset($data['synonyms'])) {
                $tool->synonyms()->delete();
                foreach (array_unique($data['synonyms']) as $synonym) {
                    $tool->synonyms()->create(['synonym' => $synonym]);
                }
            }
            Audit::record('admin.tool_updated', 'tool', $tool->id);
        });

        return back()->with('success', 'Metadata tool diperbarui.');
    }

    public function feedback()
    {
        return Inertia::render('Admin', ['section' => 'feedback', 'records' => FeedbackReport::with('attachments')->latest()->paginate(20)]);
    }

    public function updateFeedback(Request $request, FeedbackReport $feedback)
    {
        $data = $request->validate(['status' => ['required', 'in:open,in_progress,resolved,closed'], 'admin_reply' => ['nullable', 'string', 'max:10000']]);
        $feedback->update($data);
        Audit::record('admin.feedback_updated', 'feedback', $feedback->id, ['status' => $data['status']]);

        return back()->with('success', 'Status laporan diperbarui.');
    }

    public function content(Request $request, string $kind = 'articles')
    {
        $model = $this->contentModel($kind);

        return Inertia::render('Admin', ['section' => $kind, 'records' => $model::latest()->paginate(20), 'tools' => Tool::all(['id', 'name'])]);
    }

    public function saveContent(Request $request, string $kind, ?int $id = null)
    {
        $model = $this->contentModel($kind);
        $record = $id ? $model::findOrFail($id) : new $model;
        $rules = ['title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:200000'], 'title_en' => ['nullable', 'string', 'max:180'], 'body_en' => ['nullable', 'string', 'max:200000']];
        if ($kind === 'announcements') {
            $rules += ['banner' => ['boolean'], 'published_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:published_at']];
        } else {
            $rules += ['slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique($record->getTable(), 'slug')->ignore($id)], 'status' => ['required', 'in:draft,published']];
        }
        if ($kind === 'articles') {
            $rules += ['excerpt' => ['nullable', 'string', 'max:500'], 'excerpt_en' => ['nullable', 'string', 'max:500'], 'published_at' => ['nullable', 'date'], 'tool_id' => ['nullable', 'exists:tools,id']];
        }
        $data = $request->validate($rules);
        if ($kind === 'articles') {
            $data['author_id'] = $request->user()->id;
            if ($data['status'] === 'published' && ! ($data['published_at'] ?? null)) {
                $data['published_at'] = now();
            }
        }
        $record->fill($data)->save();
        Cache::forget('landing');
        Audit::record('admin.content_saved', $kind, $record->id);

        return back()->with('success', 'Konten disimpan.');
    }

    public function deleteContent(string $kind, int $id)
    {
        $model = $this->contentModel($kind);
        $record = $model::findOrFail($id);
        $record->delete();
        Audit::record('admin.content_deleted', $kind, $id);

        return back()->with('success', 'Konten dihapus.');
    }

    private function contentModel(string $kind): string
    {
        return match ($kind) {
            'articles' => DocumentationArticle::class,'pages' => CmsPage::class,'announcements' => Announcement::class,default => abort(404)
        };
    }

    public function media(Request $request)
    {
        return Inertia::render('Admin', ['section' => 'media', 'records' => MediaFile::when($request->q, fn ($q, $term) => $q->where('original_name', 'like', '%'.substr($term, 0, 100).'%'))->latest()->paginate(24)]);
    }

    public function logs()
    {
        return Inertia::render('Admin', ['section' => 'logs', 'records' => DB::table('audit_logs')->latest('id')->paginate(30)]);
    }

    public function settings()
    {
        return Inertia::render('Admin', ['section' => 'settings', 'settings' => SiteSetting::all()->pluck('value', 'key'), 'records' => []]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate(['hero_title' => ['required', 'string', 'max:180'], 'hero_description' => ['required', 'string', 'max:500'], 'hero_title_en' => ['nullable', 'string', 'max:180'], 'hero_description_en' => ['nullable', 'string', 'max:500'], 'maintenance_enabled' => ['boolean'], 'maintenance_message' => ['nullable', 'string', 'max:500'], 'maintenance_until' => ['nullable', 'date']]);
        SiteSetting::updateOrCreate(['key' => 'landing'], ['value' => ['title' => $data['hero_title'], 'description' => $data['hero_description'], 'title_en' => $data['hero_title_en'] ?? null, 'description_en' => $data['hero_description_en'] ?? null]]);
        SiteSetting::updateOrCreate(['key' => 'maintenance'], ['value' => ['enabled' => $request->boolean('maintenance_enabled'), 'message' => $data['maintenance_message'] ?? 'Sedang dalam pemeliharaan.', 'until' => $data['maintenance_until'] ?? null]]);
        Audit::record('admin.settings_updated');

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
