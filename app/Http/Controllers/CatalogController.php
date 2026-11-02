<?php
namespace App\Http\Controllers;
use App\Models\{Tool,ToolCategory,DocumentationArticle,Announcement,SiteSetting};
use App\Generators\GeneratorRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
class CatalogController extends Controller
{
    public function home()
    {
        return Inertia::render('Home',[
            'tools'=>Tool::with('category')->orderBy('sort_order')->get(), 'categories'=>ToolCategory::withCount('tools')->get(),
            'stats'=>['tools'=>Tool::count(),'ready'=>0,'generated'=>DB::table('script_histories')->count()],
            'landing'=>SiteSetting::where('key','landing')->value('value'),
            'announcements'=>Announcement::published()->where('banner',true)->latest()->limit(3)->get(),
        ]);
    }
    public function index(Request $request)
    {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'category'=>['nullable','string','max:120']]);
        $q=trim($data['q']??''); $category=$data['category']??'';
        $tools=Tool::with('category')->when($q,fn($query)=>$query->where(function($query) use ($q) {
            $query->where('name','like',"%{$q}%")->orWhere('description','like',"%{$q}%")->orWhereHas('synonyms',fn($s)=>$s->where('synonym','like',"%{$q}%"));
        }))->when($category,fn($query)=>$query->whereHas('category',fn($c)=>$c->where('slug',$category)))->orderBy('sort_order')->paginate(12)->withQueryString();
        return Inertia::render('Catalog',['tools'=>$tools,'categories'=>ToolCategory::all(),'filters'=>['q'=>$q,'category'=>$category]]);
    }
    public function show(Tool $tool, Request $request, GeneratorRegistry $registry)
    {
        return Inertia::render('Tool',['tool'=>$tool->load('category'),'schema'=>$request->user()?$registry->module($tool->generator_id)->schema():null,'available'=>$registry->isPublished($tool->generator_id),'documentation'=>DocumentationArticle::published()->where('tool_id',$tool->id)->get(['title','slug','excerpt'])]);
    }
    public function search(Request $request)
    {
        $data=$request->validate(['q'=>['required','string','min:2','max:100'],'category'=>['nullable','string','max:120']]);
        $q=$data['q'];
        $tools=Tool::when($data['category']??null,fn($query,$category)=>$query->whereHas('category',fn($c)=>$c->where('slug',$category)))
            ->where(fn($query)=>$query->where('name','like',"%{$q}%")->orWhereHas('synonyms',fn($s)=>$s->where('synonym','like',"%{$q}%")))->limit(8)->get(['name','slug','status']);
        return response()->json(['tools'=>$tools,'articles'=>DocumentationArticle::published()->where('title','like',"%{$q}%")->limit(5)->get(['title','slug'])]);
    }
}
