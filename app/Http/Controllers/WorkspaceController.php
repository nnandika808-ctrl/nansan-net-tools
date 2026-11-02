<?php
namespace App\Http\Controllers;
use App\Models\{Tool,UserPreset,ScriptHistory,Announcement};
use App\Generators\GeneratorRegistry;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate};
use Inertia\Inertia;
class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $user=$request->user();
        return Inertia::render('Dashboard',[
            'stats'=>['histories'=>$user->histories()->count(),'presets'=>$user->presets()->count(),'favorites'=>DB::table('favorites')->where('user_id',$user->id)->count()],
            'histories'=>$user->histories()->with('tool')->latest()->limit(6)->get(['id','user_id','tool_id','ros_version','generator_version','created_at']),
            'favorites'=>Tool::whereIn('id',DB::table('favorites')->where('user_id',$user->id)->select('tool_id'))->get(),
            'announcements'=>Announcement::published()->latest()->limit(10)->get()->map(fn($a)=>[...$a->toArray(),'read'=>DB::table('announcement_reads')->where('user_id',$user->id)->where('announcement_id',$a->id)->exists()]),
            'welcome'=>!$user->welcome_seen_at,
        ]);
    }
    public function welcome(Request $request) { $request->user()->forceFill(['welcome_seen_at'=>now()])->save(); return back(); }
    public function favorite(Request $request, Tool $tool)
    {
        $query=DB::table('favorites')->where('user_id',$request->user()->id)->where('tool_id',$tool->id);
        if ($query->exists()) $query->delete(); else DB::table('favorites')->insertOrIgnore(['user_id'=>$request->user()->id,'tool_id'=>$tool->id,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Favorit diperbarui.');
    }
    public function histories(Request $request) { return Inertia::render('Records',['kind'=>'histories','records'=>$request->user()->histories()->with('tool')->latest()->paginate(15)]); }
    public function presets(Request $request) { return Inertia::render('Records',['kind'=>'presets','records'=>$request->user()->presets()->with('tool')->latest()->paginate(15)]); }
    public function storePreset(Request $request, Tool $tool, GeneratorRegistry $registry)
    {
        abort_unless($registry->isPublished($tool->generator_id),423,'Preset tersedia setelah generator dipublikasikan.');
        $data=$request->validate(['name'=>['required','string','max:100'],'input'=>['required','array']]);
        $input=$registry->module($tool->generator_id)->validate($data['input']);
        $request->user()->presets()->create(['tool_id'=>$tool->id,'name'=>$data['name'],'input'=>$input,'generator_version'=>'1.0.0']);
        return back()->with('success','Preset disimpan.');
    }
    public function updatePreset(Request $request, UserPreset $preset, GeneratorRegistry $registry)
    {
        Gate::authorize('update',$preset);
        $data=$request->validate(['name'=>['required','string','max:100'],'input'=>['sometimes','array']]);
        if (isset($data['input'])) $data['input']=$registry->module($preset->tool->generator_id)->validate($data['input']);
        $preset->update($data); return back()->with('success','Preset diperbarui.');
    }
    public function deletePreset(UserPreset $preset) { Gate::authorize('delete',$preset); $preset->delete(); return back()->with('success','Preset dihapus.'); }
    public function deleteHistory(ScriptHistory $history) { Gate::authorize('delete',$history); $history->delete(); return back()->with('success','Riwayat dihapus.'); }
    public function download(ScriptHistory $history)
    {
        Gate::authorize('view',$history); return response($history->output,200,['Content-Type'=>'text/plain; charset=UTF-8','Content-Disposition'=>'attachment; filename="nansan-'.$history->id.'.rsc"']);
    }
    public function readAnnouncement(Request $request, Announcement $announcement)
    {
        abort_unless(Announcement::published()->whereKey($announcement->id)->exists(),404);
        DB::table('announcement_reads')->updateOrInsert(['user_id'=>$request->user()->id,'announcement_id'=>$announcement->id],['read_at'=>now()]); return back();
    }
}
